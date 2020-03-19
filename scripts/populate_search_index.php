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
        echo "A language to scan is required.\n";
        exit(1);
    }
    $lang = $options['lang'];

    if (empty($options['source'])) {
        echo "A source is required.\n";
        exit(1);
    }
    $source = $options['source'];

    if (empty($options['url-prefix'])) {
        echo "A url-prefix is required.\n";
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

    setMapping($urlPrefix, $lang);

    foreach ($matcher as $file) {
        $skip = false;
        foreach (FILE_EXCLUSIONS as $exclusion) {
            if (preg_match($exclusion, $file) === 1) {
                echo "\nSkipping $file\n";
                $skip = true;
                break;
            }
        }

        if (!$skip) {
            updateIndex($urlPrefix, $lang, $source, $file);
        }
    }

    echo "\nIndex update complete\n";
}

function getIndexName($urlPrefix, $lang)
{
    $indexName = trim(str_replace('/', '-', $urlPrefix), '-');
    return implode('-', [ES_INDEX_PREFIX, $indexName, $lang]);
}

function setMapping($urlPrefix, $lang)
{
    $indexName = getIndexName($urlPrefix, $lang);
    echo "Creating index.\n";
    $url = implode('/', array(ES_HOST, $indexName));
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
    $data = json_encode(['mappings' => ['_doc' => $mapping]]);
    echo "Updating mapping.\n";
    $url = implode('/', array(ES_HOST, $indexName, '_mapping', '_doc'));
    doRequest($url, CURLOPT_PUT, $data);
}

/**
 * Update the index for a given language
 *
 * @param string $urlPrefix The url prefix for the generated files.
 * @param string $lang The language to update, e.g. "en".
 * @param string $source The source path
 * @param string $file The file path.
 * @param RecursiveDirectoryIterator $file The file to load data from.
 * @return void
 */
function updateIndex($urlPrefix, $lang, $source, $file)
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

    $indexName = getIndexName($urlPrefix, $lang);
    $url = implode('/', array(ES_HOST, $indexName, '_doc', $id));

    $data = json_encode([
        'contents' => $fileData['contents'],
        'title' => $fileData['title'],
        'url' => $path,
    ]);
    echo "Sending request:\n\tfile: $file\n\turl: $url\n";
    doRequest($url, CURLOPT_PUT, $data);

    echo "Sent $file\n";
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
 * @param int $method curl opt value for the method.
 * @param string | null $body The body to send if necessary.
 */
function doRequest($url, $method, $body = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, $method, true);
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

    if ($metadata['http_code'] > 400 || !$metadata['http_code']) {
        echo "[ERROR] Failed to complete request.\n";
        var_dump($response);
        exit(2);
    }
    curl_close($ch);
    if ($fh !== null) {
        fclose($fh);
    }
}

main();
