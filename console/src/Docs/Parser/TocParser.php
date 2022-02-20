<?php
declare(strict_types=1);

namespace App\Docs\Parser;

use App\Docs\Html\Document;
use App\Docs\Toc\ExternalReference;
use App\Docs\Toc\InternalReference;
use App\Docs\Toc\Toc;
use RuntimeException;

/**
 * Parses table of contents files.
 */
class TocParser
{
    /**
     * Parses a TOC file.
     *
     * @param string $file The path to the TOC file to parse.
     * @param string $contentBasePath The base file path for the internal references in the TOC.
     * @return \App\Docs\Toc\Toc
     */
    public function parse(string $file, string $contentBasePath): Toc
    {
        $doc = new Document($file);

        $internal = $this->extractInternalReferences($doc, $contentBasePath);
        $external = $this->extractExternalReferences($doc);

        return new Toc($internal, $external);
    }

    /**
     * Extracts internal references from an HTML document.
     *
     * @param \App\Docs\Html\Document $document The HTML document from which to extract the references.
     * @param string $contentBasePath The base file path for the internal references.
     * @return \App\Docs\Toc\InternalReference[]
     */
    protected function extractInternalReferences(Document $document, string $contentBasePath): array
    {
        $links = $document->query(
            "//*[@class and contains-token(@class, 'document-body')]" .
            "//*[@class and contains-token(@class, 'toctree-wrapper')]" .
            "//a[@class and contains-token(@class, 'reference') and contains-token(@class, 'internal')]"
        );

        $internal = [];
        foreach ($links as $link) {
            /** @var \DOMElement $link */
            $href = trim($link->getAttribute('href'));
            if (
                mb_substr($href, -5) !== '.html' ||
                mb_strpos($href, '://') !== false
            ) {
                continue;
            }

            $internal[] = ltrim($href, '/');
        }
        $internal = array_values(array_unique($internal));

        return array_map(
            function ($url) use ($contentBasePath) {
                $filePath = realpath("{$contentBasePath}/{$url}");
                if (!$filePath) {
                    throw new RuntimeException(
                        "The internal reference `{$contentBasePath}/{$url}` could not be resolved."
                    );
                }

                if (mb_strpos($filePath, $contentBasePath) !== 0) {
                    throw new RuntimeException(
                        "The internal reference `{$url}` points to outside the content base path `{$contentBasePath}`."
                    );
                }

                return new InternalReference($url, $filePath);
            },
            $internal
        );
    }

    /**
     * Extracts external references from an HTML document.
     *
     * @param \App\Docs\Html\Document $document The HTML document from which to extract the references.
     * @return \App\Docs\Toc\ExternalReference[]
     */
    protected function extractExternalReferences(Document $document): array
    {
        $links = $document->query(
            "//*[@class and contains-token(@class, 'document-body')]" .
            "//*[@class and contains-token(@class, 'toctree-wrapper')]" .
            "//a[@class and contains-token(@class, 'reference') and contains-token(@class, 'external')]"
        );

        $external = [];
        foreach ($links as $link) {
            /** @var \DOMElement $link */
            $href = trim($link->getAttribute('href'));
            if (mb_strpos($href, '://') === false) {
                continue;
            }

            $external[mb_strtolower($href)] = new ExternalReference($href, trim($link->textContent));
        }

        return array_values($external);
    }
}
