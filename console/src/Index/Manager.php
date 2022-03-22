<?php
declare(strict_types=1);

namespace App\Index;

use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Utility\Text;
use stdClass;

/**
 * Provides index operations functionality.
 */
class Manager
{
    /**
     * The host URL.
     *
     * @var string
     */
    protected $host;

    /**
     * Constructor.
     *
     * @param string $host The host URL.
     */
    public function __construct(string $host)
    {
        $this->host = $host;
    }

    /**
     * Returns a build index name.
     *
     * A build index consists of an index alias with a timestamp appended.
     *
     * @param string $indexPrefix The index prefix.
     * @param string $urlPrefix The URL prefix.
     * @param string $lang The language.
     * @return string
     */
    public function getBuildIndexName(string $indexPrefix, string $urlPrefix, string $lang): string
    {
        $indexName = $this->getIndexAliasName($indexPrefix, $urlPrefix, $lang);

        return $indexName . '-' . time();
    }

    /**
     * Returns an index alias.
     *
     * @param string $indexPrefix The index prefix.
     * @param string $urlPrefix The URL prefix.
     * @param string $lang The language.
     * @return string
     */
    public function getIndexAliasName(string $indexPrefix, string $urlPrefix, string $lang): string
    {
        $indexName = trim(Text::slug($urlPrefix, ''), '-');

        return implode('-', [$indexPrefix, $indexName, $lang]);
    }

    /**
     * Returns the target index for the given alias.
     *
     * While there can be multiple identical aliases, this method will
     * return only the most recently added one.
     *
     * @param string $alias The alias for which to obtain its target index.
     * @return string|null The target index name, or `null` in case no target index could be obtained.
     */
    public function getAliasTargetIndex(string $alias): ?string
    {
        $client = new Client();
        $response = $client->get($this->buildUrl('_alias', $alias));

        if ($response->getStatusCode() === 404) {
            return null;
        }

        $this->validateResponse($response, 200);

        $aliases = array_keys($response->getJson());
        natcasesort($aliases);

        return (string)end($aliases);
    }

    /**
     * Returns a list of orphaned indices for the given alias.
     *
     * An index is an orphan when it doesn't match the alias' current
     * target index, but matches the build index name pattern.
     *
     * @param string $alias The alias for which to obtain its orphaned indices.
     * @return string[] A list of orphaned indices.
     */
    public function getOrphanedIndicesForAlias(string $alias): array
    {
        $currentTarget = $this->getAliasTargetIndex($alias);

        $client = new Client();
        $response = $client->get($this->buildUrl('_cat', 'indices', "{$alias}-*", '?s=index'), [], [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->validateResponse($response, 200);

        $data = array_filter(
            $response->getJson(),
            function (array $index) use ($alias, $currentTarget) {
                if (
                    // Indices are already filtered, but the `*` wildcard matches any character,
                    // not just numbers.
                    preg_match('/^' . preg_quote($alias, '/') . '-\d+$/', $index['index']) === 1 &&
                    $index['index'] !== $currentTarget
                ) {
                    return true;
                }

                return false;
            }
        );

        return array_column($data, 'index');
    }

    /**
     * Creates an index.
     *
     * @param string $name The name of the index to create.
     * @param array<string, mixed> $settings The index creation settings.
     * @param array<string, mixed> $properties The index property mapping.
     * @return void
     */
    public function createIndex(string $name, array $settings, array $properties): void
    {
        $client = new Client();
        $response = $client->put(
            $this->buildUrl($name, '?include_type_name=true'),
            json_encode([
                'settings' => $settings,
                'mappings' => [
                    '_doc' => [
                        'properties' => $properties,
                    ],
                ],
            ]),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        $this->validateResponse($response, 200);
    }

    /**
     * Deletes an index.
     *
     * @param string $name The name of the index to delete.
     * @return void
     */
    public function deleteIndex(string $name): void
    {
        $client = new Client();
        $response = $client->delete($this->buildUrl($name));

        $this->validateResponse($response, 200);
    }

    /**
     * Refreshes an index.
     *
     * @param string $name The name of the index to refresh.
     * @return void
     */
    public function refreshIndex(string $name): void
    {
        $client = new Client();
        $response = $client->post($this->buildUrl($name, '_refresh'));

        $this->validateResponse($response, 200);
    }

    /**
     * Sets an alias for the given index.
     *
     * Optionally removes a possibly existing alias connection to another index.
     *
     * @param string $index The index for which to set the alias.
     * @param string $alias The name of the alias.
     * @param string|null $oldIndex The name of an index to disconnect from the given alias.
     * @return void
     */
    public function setAlias(string $index, string $alias, ?string $oldIndex = null): void
    {
        $actions = [];
        if ($oldIndex) {
            $actions[] = [
                'remove' => [
                    'index' => $oldIndex,
                    'alias' => $alias,
                ],
            ];
        }
        $actions[] = [
            'add' => [
                'index' => $index,
                'alias' => $alias,
            ],
        ];

        $client = new Client();
        $response = $client->post(
            $this->buildUrl('_aliases'),
            json_encode(['actions' => $actions]),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        $this->validateResponse($response, 200);
    }

    /**
     * Stores a document in the given index.
     *
     * @param \App\Index\Document $document The document to store.
     * @param string $index The name of the index in which to store the document.
     * @return void
     */
    public function storeDocument(Document $document, string $index): void
    {
        $client = new Client();
        $response = $client->put(
            $this->buildUrl($index, '_doc', $document->getId()),
            json_encode($document->getData()),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        $this->validateResponse($response, 201);
    }

    /**
     * Returns "all" documents for the given index.
     *
     * "all" here means the max possible, which is `10000`.
     *
     * @param string $index The name of the index from which to obtain the documents.
     * @return \App\Index\Document[]
     */
    public function getAllDocuments(string $index): array
    {
        $client = new Client();
        $response = $client->post(
            $this->buildUrl($index, '_search'),
            json_encode([
                'size' => 10000,
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ]),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        $this->validateResponse($response, 200);

        return array_map(
            function (array $hit) {
                return new Document($hit['_id'], $hit['_source']);
            },
            $response->getJson()['hits']['hits']
        );
    }

    /**
     * Builds an endpoint URL.
     *
     * All parts are being concatenated using a forward slash.
     *
     * @param string ...$parts The endpoint URL parts.
     * @return string The endpoint URL.
     */
    protected function buildUrl(string ...$parts): string
    {
        return implode('/', array_merge([$this->host], $parts));
    }

    /**
     * Validates a response with respect to the given expected HTTP
     * status codes.
     *
     * @param \Cake\Http\Client\Response $response The response to validate.
     * @param int ...$expectedStatuses The expected/valid HTTP status codes.
     * @return void
     * @throws \App\Index\ErrorResponseException In case the response HTTP code doesn't match any of the given expected
     *  status codes.
     */
    protected function validateResponse(Response $response, int ...$expectedStatuses): void
    {
        if (in_array($response->getStatusCode(), $expectedStatuses, true)) {
            return;
        }

        $body = (array)$response->getJson();

        $message = $response->getReasonPhrase();
        if (isset($body['error']) && is_string($body['error'])) {
            $message = $body['error'];
        }
        if (isset($body['error']) && is_array($body['error'])) {
            $message = $body['error']['reason'];
        }

        $status = $response->getStatusCode();
        if (isset($body['status'])) {
            $status = $body['status'];
        }

        throw new ErrorResponseException("[{$status}] {$message}", $status);
    }
}
