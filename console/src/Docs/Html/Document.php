<?php
declare(strict_types=1);

namespace App\Docs\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use RuntimeException;

/**
 * Provides access to HTML document contents.
 */
class Document
{
    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var \DOMDocument
     */
    protected $dom;

    /**
     * @var \DOMXPath
     */
    protected $xpath;

    /**
     * Returns the path to the HTML file.
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Constructor.
     *
     * @param string $filePath The path to the HTML file.
     * @throws \RuntimeException In case the file cannot be read/loaded.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;

        $this->dom = new DOMDocument();

        $userInternalErrors = libxml_use_internal_errors(true);
        $this->dom->loadHTMLFile($filePath);
        $errors = libxml_get_errors();
        libxml_use_internal_errors($userInternalErrors);

        $validTags =
            'article|aside|bdi|details|dialog|figcaption|figure|footer|header|main|mark|menuitem|meter|nav|' .
            'path|progress|rp|rt|ruby|section|summary|time|wbr|datalist|keygen|output|canvas|svg|audio|' .
            'embed|source|track|video';

        foreach ($errors as $error) {
            if (
                $error->code !== 801 ||
                !preg_match("/Tag ($validTags) invalid/i", $error->message)
            ) {
                $message = trim($error->message);
                throw new RuntimeException("Loading `{$filePath}` failed: {$message}");
            }
        }

        $this->xpath = new DOMXPath($this->dom);
    }

    /**
     * Evaluates an XPath expression.
     *
     * @param string $expression The expression to evaluate.
     * @param \DOMNode|null $contextNode The node in whose context to evaluate the expression.
     * @return \DOMNodeList<\DOMNode>
     */
    public function query(string $expression, ?DOMNode $contextNode = null): DOMNodeList
    {
        $processedExpression = $this->processXPathExpression($expression);
        if (!$processedExpression) {
            throw new RuntimeException("Couldn't process XPath expression `{$expression}`.");
        }

        $result = $this->evaluateXPathExpression($processedExpression, $contextNode);
        if (!$result) {
            throw new RuntimeException("Couldn't evaluate XPath expression `{$processedExpression}`.");
        }

        return $result;
    }

    /**
     * Searches for an element by its ID.
     *
     * @param string $elementId The ID of the element to search.
     * @return \DOMElement|null
     */
    public function getElementById(string $elementId): ?DOMElement
    {
        return $this->dom->getElementById($elementId);
    }

    /**
     * Processes XPath expressions and applies transformations.
     *
     * @param string $expression The expression to process.
     * @return string|null
     */
    protected function processXPathExpression(string $expression): ?string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';

        return preg_replace(
            '/contains-token\(\s*(.+?)\s*,\s*(.+?)\s*\)/',
            'contains(' .
                "concat(' ', normalize-space(translate(\\1, '$uppercase', '$lowercase')), ' ')," .
                "concat(' ', normalize-space(translate(\\2, '$uppercase', '$lowercase')), ' ')" .
            ')',
            $expression
        );
    }

    /**
     * Evaluates an XPath expression.
     *
     * @param string $expression The expression to evaluate.
     * @param \DOMNode|null $contextNode The node in whose context to evaluate the expression.
     * @return \DOMNodeList<\DOMNode>|false
     */
    protected function evaluateXPathExpression(string $expression, ?DOMNode $contextNode = null)
    {
        // phpcs:ignore Generic.PHP.NoSilencedErrors,CakePHP.Formatting.BlankLineBeforeReturn
        return @$this->xpath->query($expression, $contextNode, true);
    }
}
