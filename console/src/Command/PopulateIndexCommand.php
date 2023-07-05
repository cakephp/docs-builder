<?php
declare(strict_types=1);

namespace App\Command;

use App\Docs\Parser\ContentParser;
use App\Docs\Parser\TocParser;
use App\Index\Document;
use App\Index\ErrorResponseException;
use App\Index\Manager;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Exception\StopException;
use Cake\Utility\Text;
use Exception;

/**
 * Populates the Elasticsearch index.
 */
class PopulateIndexCommand extends BaseCommand
{
    public const FILE_EXCLUSIONS = [
        '/^404\.html$/',
        '/^contents\.html$/',
        '/^epub-contents\.html$/',
        '/^pdf-contents\.html$/',
        '/^genindex\.html$/',
        '/^php-modindex\.html$/',
        '/^search\.html$/',
        '/^topics\.html$/',
        '/^glossary\.html$/',
    ];

    public const PRIORITIES = [
        '/appendices/' => Document::PRIORITY_LOW,
    ];

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('source', [
                'required' => true,
                'help' => 'The absolute path to the generated HTML docs, eg `/data/build/en`.',
            ])
            ->addOption('lang', [
                'required' => true,
                'help' => 'The language code of the generated docs, eg `en`.',
            ])
            ->addOption('url-prefix', [
                'required' => true,
                'help' => 'The URL prefix.',
            ])
            ->addOption('host', [
                'help' => 'The Elasticsearch host URL.',
                'default' => 'https://ci.cakephp.org:9200',
            ])
            ->addOption('index-prefix', [
                'help' => 'The Elasticsearch index prefix.',
                'default' => 'cake-docs',
            ]);

        return $parser;
    }

    /**
     * Executes the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int The exit code.
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $host = (string)$args->getOption('host');
        $source = (string)$args->getOption('source');
        $urlPrefix = rtrim(trim((string)$args->getOption('url-prefix')), '/');
        $indexPrefix = trim((string)$args->getOption('index-prefix'));
        $lang = (string)$args->getOption('lang');

        $indexManager = $this->getIndexManager($host);

        $buildIndex = $indexManager->getBuildIndexName($indexPrefix, $urlPrefix, $lang);
        $indexAlias = $indexManager->getIndexAliasName($indexPrefix, $urlPrefix, $lang);

        try {
            $this->deleteOrphanedIndices($io, $indexManager, $indexAlias);

            $currentTarget = $this->ensureIndexAlias($io, $indexManager, $indexAlias);

            $this->createBuildIndex($io, $indexManager, $buildIndex);
            $this->populateBuildIndex($io, $indexManager, $buildIndex, $source, $urlPrefix, $lang);

            $this->setIndexAlias($io, $indexManager, $buildIndex, $indexAlias, $currentTarget);
        } catch (Exception $exception) {
            $code = static::CODE_ERROR;
            if ($exception instanceof StopException) {
                $code = $exception->getCode();
            } else {
                $io->error($exception->getMessage());
                $io->error($exception->getTraceAsString());
            }

            return $code;
        }

        $io->success('Index update complete.');

        return static::CODE_SUCCESS;
    }

    /**
     * Delete orphaned indices.
     *
     * Orphaned indices can emerge when swapping aliases, or deleting
     * old indices after swapping aliases fails.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Index\Manager $indexManager The index manager.
     * @param string $alias The alias whose orphaned indices to delete.
     * @return void
     */
    protected function deleteOrphanedIndices(ConsoleIo $io, Manager $indexManager, string $alias): void
    {
        $io->out("Deleting orphaned indices for index alias `{$alias}`.");
        $orphanedIndices = $indexManager->getOrphanedIndicesForAlias($alias);
        if (!$orphanedIndices) {
            $io->info('No orphaned indices found.');
        }

        foreach ($orphanedIndices as $orphanedIndex) {
            $io->out("Deleting orphaned index `{$orphanedIndex}`.");
            try {
                $indexManager->deleteIndex($orphanedIndex);
            } catch (ErrorResponseException $exception) {
                $io->warning("The index `{$orphanedIndex}` could not be deleted: {$exception->getMessage()}");
            }
        }
    }

    /**
     * Ensures that either an alias is present, and returns its target index,
     * or deletes the old non-aliased index that has the same name as the
     * alias.
     *
     * No current target index being found could be because of an error,
     * as well as because migration to aliases hasn't been performed yet.
     * In any case this method will delete the index. This will incur
     * a small amount of downtime, but it should be rare.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Index\Manager $indexManager The index manager.
     * @param string $alias The name of the alias to check.
     * @return string|null The current alias' index target, or `null` in case no target index could be obtained.
     */
    protected function ensureIndexAlias(ConsoleIo $io, Manager $indexManager, string $alias): ?string
    {
        $io->out("Checking index alias `{$alias}`.");
        $currentTargetIndex = $indexManager->getAliasTargetIndex($alias);
        if ($currentTargetIndex) {
            $io->out("Alias `{$alias}` is currently pointing at `{$currentTargetIndex}`.");

            return $currentTargetIndex;
        }

        $io->info('No index alias exists. Migrating to index aliases.');

        $io->out("Deleting old, non-aliased index `{$alias}`.");
        try {
            $indexManager->deleteIndex($alias);
        } catch (ErrorResponseException $exception) {
            $io->warning(
                "The index `{$alias}` could not be deleted, " .
                "the previous build might have failed: {$exception->getMessage()}"
            );
        }

        return null;
    }

    /**
     * Creates the build index.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Index\Manager $indexManager The index manager.
     * @param string $index The name of the build index to create.
     * @return void
     */
    protected function createBuildIndex(ConsoleIo $io, Manager $indexManager, string $index): void
    {
        $io->out("Creating build index `{$index}`.");

        $settings = [
            'number_of_shards' => 1,
        ];
        $properties = [
            'type' => ['type' => 'keyword', 'index' => false],
            'priority' => ['type' => 'keyword'],
            'url' => ['type' => 'keyword', 'index' => false],
            'page_url' => ['type' => 'keyword'],
            'level' => ['type' => 'short'],
            'max_level' => ['type' => 'short'],
            'position' => ['type' => 'short'],
            'max_position' => ['type' => 'short'],
            'hierarchy' => ['type' => 'text', 'fielddata' => true],
            'title' => ['type' => 'text', 'fielddata' => true],
            'contents' => ['type' => 'text', 'fielddata' => true],
        ];

        try {
            $indexManager->createIndex($index, $settings, $properties);
        } catch (ErrorResponseException $exception) {
            $io->abort("The `{$index}` index could not be created: {$exception->getMessage()}");
        }
    }

    /**
     * Populates the build index.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Index\Manager $indexManager The index manager.
     * @param string $index The name of the build index to populate.
     * @param string $source The path where the HTML docs source files are located.
     * @param string $urlPrefix The URL prefix.
     * @param string $lang The language.
     * @return void
     */
    protected function populateBuildIndex(
        ConsoleIo $io,
        Manager $indexManager,
        string $index,
        string $source,
        string $urlPrefix,
        string $lang
    ): void {
        $io->out("Populating build index `{$index}`.");

        $contentBasePath = realpath($source);
        if (!$contentBasePath) {
            $io->abort("The source path `{$source}` could not be found.");
        }
        /** @var string $contentBasePath */

        $tocFilePath = "{$contentBasePath}/contents.html";
        if (!file_exists($tocFilePath)) {
            $io->abort("The TOC file `{$tocFilePath}` could not be found.");
        }

        $tocParser = new TocParser();
        $toc = $tocParser->parse($tocFilePath, $contentBasePath);

        $internalReferences = $toc->getInternalReferences();
        if (empty($internalReferences)) {
            $io->abort("No internal references found in `{$tocFilePath}`.");
        }

        $contentParser = new ContentParser();

        foreach ($internalReferences as $internalReference) {
            $url = $internalReference->getUrl();

            foreach (static::FILE_EXCLUSIONS as $exclusion) {
                if (preg_match($exclusion, $url) === 1) {
                    $io->info("Skipping internal reference `{$url}` because of exclusion rule `{$exclusion}`.");
                    continue 2;
                }
            }

            $priority = Document::PRIORITY_NORMAL;
            foreach (static::PRIORITIES as $regex => $value) {
                if (preg_match($regex, $url) === 1) {
                    $priority = $value;
                    break;
                }
            }

            $pageFilePath = $internalReference->getFilePath();
            $page = $contentParser->parse($pageFilePath);

            $sections = $page->getSections();
            if (!$sections) {
                $io->warning(
                    "No sections found in `{$pageFilePath}`. This might be a page that contains only a TOC tree."
                );
            }

            $maxLevel = 0;
            $maxPosition = 0;
            foreach ($sections as $section) {
                $maxLevel = max($maxLevel, $section->getLevel());
                $maxPosition = max($maxPosition, $section->getPosition());
            }

            foreach ($sections as $section) {
                $document = new Document(
                    Text::slug("{$internalReference->getUrl()}#{$section->getAnchor()}"),
                    [
                        'type' => Document::TYPE_INTERNAL,
                        'priority' => $priority,
                        'url' => "{$urlPrefix}/{$lang}/{$internalReference->getUrl()}#{$section->getAnchor()}",
                        'page_url' => "{$urlPrefix}/{$lang}/{$internalReference->getUrl()}",
                        'level' => $section->getLevel(),
                        'max_level' => $maxLevel,
                        'position' => $section->getPosition(),
                        'max_position' => $maxPosition,
                        'hierarchy' => array_merge($page->getHierarchy(), $section->getHierarchy()),
                        'title' => $section->getTitle(),
                        'contents' => $section->getContent(),
                    ]
                );

                $io->out("Storing document for `{$document->getData()['url']}`.");

                try {
                    $indexManager->storeDocument($document, $index);
                } catch (ErrorResponseException $exception) {
                    $io->abort(
                        "Could not store the document for `{$document->getData()['url']}`: {$exception->getMessage()}"
                    );
                }
            }
        }

        foreach ($toc->getExternalReferences() as $externalReference) {
            $document = new Document(
                Text::slug($externalReference->getUrl()),
                [
                    'type' => Document::TYPE_EXTERNAL,
                    'priority' => Document::PRIORITY_NORMAL,
                    'url' => $externalReference->getUrl(),
                    'page_url' => $externalReference->getUrl(),
                    'level' => 0,
                    'max_level' => 0,
                    'position' => 0,
                    'max_position' => 0,
                    'hierarchy' => [$externalReference->getTitle()],
                    'title' => $externalReference->getTitle(),
                    'contents' => null,
                ]
            );

            $io->out("Storing document for `{$document->getData()['url']}`.");

            try {
                $indexManager->storeDocument($document, $index);
            } catch (ErrorResponseException $exception) {
                $io->abort(
                    "Could not store the document for `{$document->getData()['url']}`: {$exception->getMessage()}"
                );
            }
        }
    }

    /**
     * Sets an alias for the build index.
     *
     * Optionally removes a possibly existing alias connection to the current index.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Index\Manager $indexManager The index manager.
     * @param string $buildIndex The name of the build index.
     * @param string $alias The name of alias that should point to the build index.
     * @param string|null $currentTargetIndex The name of the index that is currently connected to the alias.
     * @return void
     */
    protected function setIndexAlias(
        ConsoleIo $io,
        Manager $indexManager,
        string $buildIndex,
        string $alias,
        ?string $currentTargetIndex
    ): void {
        $io->out("Setting alias `{$alias}` to point to `{$buildIndex}`.");

        try {
            $indexManager->setAlias($buildIndex, $alias, $currentTargetIndex);
        } catch (ErrorResponseException $exception) {
            $io->abort(
                "The alias `{$alias}` could not be set to point to `{$buildIndex}`: {$exception->getMessage()}"
            );
        }

        if ($currentTargetIndex) {
            $io->out("Deleting old build index `{$currentTargetIndex}`.");

            try {
                $indexManager->deleteIndex($currentTargetIndex);
            } catch (ErrorResponseException $exception) {
                $io->warning("The index `{$currentTargetIndex}` could not be deleted: {$exception->getMessage()}");
            }
        }
    }

    /**
     * Returns an index manager instance.
     *
     * @param string $host The host URL.
     * @return \App\Index\Manager
     */
    protected function getIndexManager(string $host): Manager
    {
        return new Manager($host);
    }
}
