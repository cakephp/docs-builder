<?php
declare(strict_types=1);

namespace App\Docs\Parser;

use App\Docs\Content\Page;
use App\Docs\Content\Section;
use App\Docs\Html\Document;
use DOMNode;
use DOMText;
use RuntimeException;

/**
 * Parses page files.
 */
class ContentParser
{
    /**
     * Parses a page file.
     *
     * @param string $file The path to the page file to parse.
     * @return \App\Docs\Content\Page
     */
    public function parse(string $file): Page
    {
        $doc = new Document($file);

        $this->cleanup($doc);

        $hierarchy = $this->extractHierarchy($doc);
        $rootSection = $this->extractRootSection($doc);
        $sections = $this->extractSections($doc, $rootSection->cloneNode(true));

        return new Page($hierarchy, $sections);
    }

    /**
     * Cleans up the document.
     *
     * @param \App\Docs\Html\Document $document The document to clean up.
     * @return void
     */
    protected function cleanup(Document $document): void
    {
        // Remove irrelevant content that could degrade the results.
        $headerLinks = $document->query("//a[@class and contains-token(@class, 'headerlink')]");
        foreach ($headerLinks as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
        $admonitionTitles = $document->query("//*[@class and contains-token(@class, 'admonition-title')]");
        foreach ($admonitionTitles as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
        $tocTrees = $document->query(
            "//*[@class and contains-token(@class, 'document-body')]" .
            "//*[@class and contains-token(@class, 'toctree-wrapper')]"
        );
        foreach ($tocTrees as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }

        // Convert code into single text nodes, otherwise the separate
        // text nodes it consists of (because of syntax highlighting)
        // would result in the text parts being separated with a space,
        // which could break up terms.
        foreach ($document->query("//code | //pre | //*[@class and contains-token(@class, 'sig-object')]") as $node) {
            $node->textContent = trim($node->textContent);
        }
    }

    /**
     * Extracts the hierarchy.
     *
     * @param \App\Docs\Html\Document $document The document from which to extract the hierarchy.
     * @return string[]
     */
    protected function extractHierarchy(Document $document): array
    {
        $sidebarNavigation = $document->getElementById('sidebar-navigation');
        if (!$sidebarNavigation) {
            throw new RuntimeException("No sidebar navigation found in `{$document->getFilePath()}`");
        }

        $hierarchy = [];
        $activeTOCLinks = $document->query(".//*[@class and contains-token(@class, 'current')]/a", $sidebarNavigation);
        foreach ($activeTOCLinks as $node) {
            $hierarchy[] = trim($node->textContent);
        }
        if (!$hierarchy) {
            throw new RuntimeException("No active TOC hierarchy found in `{$document->getFilePath()}`");
        }
        // The last crumb should be the page itself, even though the title might
        // not always be 100% identical.
        array_pop($hierarchy);

        return $hierarchy;
    }

    /**
     * Extracts the root section.
     *
     * @param \App\Docs\Html\Document $document The document from which to extract the root section.
     * @return \DOMNode
     */
    protected function extractRootSection(Document $document): DOMNode
    {
        $documentBody = $document->query("//*[@class and contains-token(@class, 'document-body')]")->item(0);
        if (!$documentBody) {
            throw new RuntimeException("No document body found in `{$document->getFilePath()}`");
        }

        $rootSection = $document
            ->query(".//section | .//*[@class and contains-token(@class, 'section')]", $documentBody)
            ->item(0);
        if (!$rootSection) {
            throw new RuntimeException("No root section found in `{$document->getFilePath()}`");
        }

        return $rootSection;
    }

    /**
     * Recursively extracts (sub)sections from the given node, where
     * the node is a section itself.
     *
     * @param \App\Docs\Html\Document $document The document that owns the node.
     * @param \DOMNode $node The (section) node from which to extract the (sub)sections.
     * @param int $level The current level.
     * @param int $position The current position.
     * @param string[] $hierarchy The parent section hierarchy.
     * @return \App\Docs\Content\Section[]
     */
    protected function extractSections(
        Document $document,
        DOMNode $node,
        int $level = 0,
        int &$position = 0,
        array $hierarchy = []
    ): array {
        if (
            !$node->attributes ||
            !$node->attributes->getNamedItem('id')
        ) {
            throw new RuntimeException("Missing section anchor in `{$document->getFilePath()}`");
        }
        $anchor = $node->attributes->getNamedItem('id')->nodeValue;

        $titleNode = $document->query('.//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $node)->item(0);
        if (!$titleNode) {
            throw new RuntimeException("Missing title node in `#{$anchor}` section.");
        }
        // Remove the title node from the content, we don't want
        // duplicate content like that.
        if ($titleNode->parentNode !== null) {
            $titleNode->parentNode->removeChild($titleNode);
        }
        $title = trim($titleNode->textContent);
        if (!$title) {
            throw new RuntimeException("Missing section title in `#{$anchor}` section.");
        }

        $hierarchy[] = $title;

        // Only process sections that actually have any content.
        // Empty sections can be an error, or the result of having
        // only TOC trees as children, which are being removed
        // earlier.
        if (trim($node->textContent) === '') {
            return [];
        }

        // Extract nested subsections.
        $subsections = [];
        while (true) {
            $subsection = $document
                ->query(".//section | .//*[@class and contains-token(@class, 'section')]", $node)
                ->item(0);
            if (!$subsection) {
                break;
            }
            if ($subsection->parentNode !== null) {
                $subsection->parentNode->removeChild($subsection);
            }
            $subsections[] = $subsection;
        }

        // Extract individual text fragments in order to be able to
        // concatenate them using a single space.
        $content = implode(' ', $this->extractTextFragments($node));
        $content = str_replace(["\r", "\n"], ' ', $content);
        $content = (string)preg_replace('/\s{2,}/', ' ', trim($content));

        $section = new Section($level, $position++, $hierarchy, $anchor, $title, $content);
        $sections = [$section];

        if ($subsections) {
            foreach ($subsections as $subsection) {
                $sections = array_merge(
                    $sections,
                    $this->extractSections($document, $subsection, $level + 1, $position, $hierarchy)
                );
            }
        }

        return $sections;
    }

    /**
     * Recursively extracts text fragments from the given node.
     *
     * @param \DOMNode $node The node from which to extract the text fragments.
     * @return string[]
     */
    protected function extractTextFragments(DOMNode $node): array
    {
        $text = [];
        foreach ($node->childNodes as $childNode) {
            /** @var \DOMNode $childNode */
            if ($childNode instanceof DOMText) {
                $text[] = trim($childNode->textContent);
                continue;
            }

            if ($childNode->hasChildNodes()) {
                $text = array_merge($text, $this->extractTextFragments($childNode));
            }
        }

        return $text;
    }
}
