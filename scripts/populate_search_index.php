#!/usr/bin/env php
<?php
/**
 * Utility script to populate the elastic search indexes
 *
 * Gets called by the Make file.
 */

// Elastic search config
define('ES_DEFAULT_HOST', 'https://ci.cakephp.org:9200');
define('ES_INDEX_PREFIX', 'cake-docs');


// file exclusion patterns
const FILE_EXCLUSIONS = [
    '/404\.rst$/',
];

/**
 * The main function
 *
 * Populates the search index for the given language.
 *
 * @param array $argv The array of CLI arguments, 1: language, 2. Elastic search host.
 * @return void
 */
function main()
{
    $options = getopt('', ['host::', 'lang:', 'url-prefix:', 'source:']);
    if (empty($options['lang'])) {
        output("A language to scan is required.");
        exit(1);
    }
    $lang = $options['lang'];

    if (empty($options['source'])) {
        output("A source is required.");
        exit(1);
    }
    $source = $options['source'];

    if (empty($options['url-prefix'])) {
        output("A url-prefix is required.");
        exit(1);
    }
    $urlPrefix = $options['url-prefix'];

    if (!empty($options['host'])) {
        define('ES_HOST', $options['host']);
    } else {
        define('ES_HOST', ES_DEFAULT_HOST);
    }

    $directory = new RecursiveDirectoryIterator($source);
    $recurser = new RecursiveIteratorIterator($directory);
    $matcher = new RegexIterator($recurser, '/\.rst/');

    $buildIndex = getBuildIndexName($urlPrefix, $lang);
    $indexAlias = getIndexAliasName($urlPrefix, $lang);

    $currentTarget = ensureAlias($indexAlias);
    setMapping($buildIndex);

    foreach ($matcher as $file) {
        $skip = false;
        foreach (FILE_EXCLUSIONS as $exclusion) {
            if (preg_match($exclusion, $file) === 1) {
                output("");
                output("Skipping $file");
                $skip = true;
                break;
            }
        }

        if (!$skip) {
            updateIndex($buildIndex, $urlPrefix, $lang, $source, $file);
        }
    }
    setAlias($buildIndex, $indexAlias, $currentTarget);

    output('---------------------');
    output("Index update complete");
    output('---------------------');
}

/**
 * Get the name of the build specific index name
 */
function getBuildIndexName($urlPrefix, $lang)
{
    $indexName = getIndexAliasName($urlPrefix, $lang);
    return $indexName . '-' . time();
}

/**
 * Generate the name of the destination index alias.
 */
function getIndexAliasName($urlPrefix, $lang)
{
    $indexName = trim(str_replace('/', '-', $urlPrefix), '-');
    return implode('-', [ES_INDEX_PREFIX, $indexName, $lang]);
}

/**
 * Get the current alias target.
 *
 * Will return null on failure.
 */
function getCurrentAliasTarget($indexAlias)
{
    $url = implode('/', array(ES_HOST, '_alias', $indexAlias));
    try {
        $response = doRequest($url, CURLOPT_HTTPGET);
    } catch (\Exception $error) {
        // Likely a 404. But if it isn't we will nuke it and start over.
        // This will incur a small amount of downtime but it should be rare.
        return null;
    }
    $data = json_decode($response, true);
    if (!$data) {
        return null;
    }

    return array_keys($data)[0];
}


/**
 * Ensure that indexAlias is an alias
 * removing any existing indexes.
 *
 * @return string|null Either the alias' current target or null.
 */
function ensureAlias($indexAlias)
{
    output("> Checking index alias {$indexAlias}");
    $currentTarget = getCurrentAliasTarget($indexAlias);
    if ($currentTarget) { 
        // If we have an alias with a target we are good to update it.
        output("> Alias {$indexAlias} is currently pointing at {$currentTarget}.");
        return $currentTarget;
    }

    output("!! No index alias exists. Migrating to index aliases.");

    output('!! Removing old index.');
    $url = implode('/', array(ES_HOST, $indexAlias));
    try {
        doRequest($url, 'DELETE');
    } catch (\Exception $e) {
        output("! Index did not exist. Likely the previous build failed.");
    }

    return null;
}

function setAlias($buildIndex, $indexAlias, $currentTarget)
{
    $actions = [];
    if ($currentTarget) {
        $actions[] = [
            "remove" => [
                "index" => $currentTarget,
                "alias" => $indexAlias,
            ],
        ];
    }
    $actions[] = [
        "add" => [
            "index" => $buildIndex,
            "alias" => $indexAlias,
        ],
    ];

    output("> Setting alias {$indexAlias} to point to {$buildIndex}");
    $url = implode('/', array(ES_HOST, '_alias'));
    $body = json_encode(['actions' => $actions]);
    doRequest($url, CURLOPT_PUT, $body);

    if ($currentTarget) {
        removeIndex($currentTarget);
    }
}

function setMapping($indexName)
{
    $url = implode('/', array(ES_HOST, $indexName));
    output("> Creating index: {$url}");
    doRequest($url, CURLOPT_PUT);

    $mapping = [
      "properties" => [
        "contents" => ["type" => "text"],
        "title" => ["type" => "keyword"],
        "url" => [
            "type" => "keyword",
            "index" => false,
        ],
      ],
    ];
    $url = implode('/', array(ES_HOST, $indexName, '_mapping', '_doc'));
    $data = json_encode(['mappings' => ['_doc' => $mapping]]);

    output("> Updating mapping: {$url}");
    doRequest($url, CURLOPT_PUT, $data);
}

function removeIndex($indexName)
{
    output("> Removing index: {$indexName}");
    $url = implode('/', array(ES_HOST, $indexName));
    doRequest($url, 'DELETE');
}

/**
 * Update the index for a given language
 *
 * @param string $indexName The index name
 * @param string $urlPrefix The url prefix for the generated files.
 * @param string $lang The language to update, e.g. "en".
 * @param string $source The source path
 * @param string $file The file path.
 * @param RecursiveDirectoryIterator $file The file to load data from.
 * @return void
 */
function updateIndex($indexName, $urlPrefix, $lang, $source, $file)
{
    $fileData = readFileData($file);
    $filename = $file->getPathName();
    $filename = substr($filename, strlen($source));
    list($filename) = explode('.', $filename);

    $path = $filename . '.html';
    $path = str_replace('//', '/', $urlPrefix . '/' . $lang . '/' . $path);

    $id = str_replace($lang . '/', '', $filename);
    $id = str_replace('/', '-', $id);
    $id = trim($id, '-');

    $url = implode('/', array(ES_HOST, $indexName, '_doc', $id));

    $data = json_encode([
        'contents' => $fileData['contents'],
        'title' => $fileData['title'],
        'url' => $path,
    ]);
    output("Sending request:\n\tfile: $file\n\turl: $url");
    doRequest($url, CURLOPT_PUT, $data);

    output("Sent $file");
}

/**
 * Read data from file
 *
 * @param string $file The file to read.
 * @return array The read data.
 */
function readFileData($file)
{
    $title = '';
    $contents = file_get_contents($file);

    // Extract the title and guess that things underlined with # or == and first in the file
    // are the title.
    preg_match('/^(.*)\n[=#]+\n/', $contents, $matches);
    if ($matches) {
        $title = $matches[1];

        // Remove the title from the indexed text.
        $contents = str_replace($matches[0], '', $contents);
    }

    // Remove title markers from the text.
    $contents = preg_replace('/\n[-=~]+\n/', '', $contents);

    return compact('contents', 'title');
}

/**
 * Send a request with curl. If the request fails the process will die.
 *
 * @param string $url
 * @param string|int $method curl opt value for the method.
 * @param string | null $body The body to send if necessary.
 */
function doRequest($url, $method, $body = null)
{
    $ch = curl_init($url);
    if (is_string($method)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    } else {
        curl_setopt($ch, $method, true);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $fh = null;
    if ($body) {
        $size = strlen($body);

        $fh = fopen('php://memory', 'rw');
        fwrite($fh, $body);
        rewind($fh);

        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
    }

    $response = curl_exec($ch);
    $metadata = curl_getinfo($ch);

    output("Sending {$method} to {$url}");
    if ($metadata['http_code'] > 400 || !$metadata['http_code']) {
        $message = "Failed to complete request to $url\nResponse Body:\n" . $response;
        throw new RuntimeException($message);
    }
    curl_close($ch);
    if ($fh !== null) {
        fclose($fh);
    }

    return $response;
}

function output($msg)
{
    echo "$msg\n";
}

main();
