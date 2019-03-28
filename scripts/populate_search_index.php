#!/usr/bin/env php
<?php
/**
 * Utility script to populate the elastic search indexes
 *
 * Gets called by the Make file.
 */

// Elastic search config
define('ES_DEFAULT_HOST', 'https://ci.cakephp.org:9200');
define('ES_INDEX', 'documentation');

/**
 * The main function
 *
 * Populates the search index for the given language.
 *
 * @param array $argv The array of CLI arguments, 1: language, 2. Elastic search host.
 * @return void
 */
function main($argv)
{
    if (empty($argv[1])) {
        echo "A source directory is required.\n";
        exit(1);
    }
    if (empty($argv[2])) {
        echo "An index name is required.\n";
        exit(1);
    }
    $sourceDir = $argv[1];
    $indexName = $argv[2];

    if (!empty($argv[3])) {
        define('ES_HOST', $argv[3]);
    } else {
        define('ES_HOST', ES_DEFAULT_HOST);
    }

    $directory = new RecursiveDirectoryIterator($sourceDir);
    $recurser = new RecursiveIteratorIterator($directory);
    $matcher = new RegexIterator($recurser, '/\.rst/');

    foreach ($matcher as $file) {
        updateIndex($indexName, $sourceDir, $file);
    }

    echo "\nIndex update complete\n";
}

/**
 * Update the index for a given language
 *
 * @param string $lang The language to update, e.g. "en".
 * @param RecursiveDirectoryIterator $file The file to load data from.
 * @return void
 */
function updateIndex($indexName, $sourceDir, $file)
{
    $fileData = readFileData($file);
    $filename = $file->getPathName();
    $filename = substr($filename, strlen($sourceDir));
    list($filename) = explode('.', $filename);

    $path = $filename . '.html';
    $id = str_replace('/', '-', $filename);
    $id = trim($id, '-');

    $url = implode('/', array(ES_HOST, ES_INDEX, $indexName, $id));

    $data = array(
        'contents' => $fileData['contents'],
        'title' => $fileData['title'],
        'url' => $path,
    );

    $data = json_encode($data);
    $size = strlen($data);

    $fh = fopen('php://memory', 'rw');
    fwrite($fh, $data);
    rewind($fh);

    echo "Sending request:\n\tfile: $file\n\turl: $url\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fh);
    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $metadata = curl_getinfo($ch);

    if ($metadata['http_code'] > 400 || !$metadata['http_code']) {
        echo "[ERROR] Failed to complete request.\n";
        var_dump($response);
        exit(2);
    }

    curl_close($ch);
    fclose($fh);

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
    $contents = file_get_contents($file);

    // Extract the title and guess that things underlined with # or == and first in the file
    // are the title.
    preg_match('/^(.*)\n[=#]+\n/', $contents, $matches);
    $title = $matches[1];

    // Remove the title from the indexed text.
    $contents = str_replace($matches[0], '', $contents);

    // Remove title markers from the text.
    $contents = preg_replace('/\n[-=~]+\n/', '', $contents);

    return compact('contents', 'title');
}

main($argv);
