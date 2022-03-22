<?php
declare(strict_types=1);

namespace App\Test\TestCase\Docs\Html;

use App\Docs\Html\Document;
use DOMNodeList;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class DocumentTest extends TestCase
{
    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->filesystem->remove(TMP . 'tests/docs');
    }

    public function testQueryContainsToken(): void
    {
        $path = TMP . 'tests/docs/test.html';
        $this->filesystem->copy(TESTS . 'data/html/test.html', $path, true);

        $document = $this
            ->getMockBuilder(Document::class)
            ->setConstructorArgs([$path])
            ->onlyMethods(['evaluateXPathExpression'])
            ->getMock();

        $document
            ->expects($this->once())
            ->method('evaluateXPathExpression')
            ->with(
                '//*[contains(' .
                    "concat(' ', normalize-space(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')), ' ')," .
                    "concat(' ', normalize-space(translate('token', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')), ' ')" .
                ')]'
            )
            ->willReturn(new DOMNodeList());

        $document->query("//*[contains-token(@class, 'token')]");
    }

    public function testQueryNonProcessableExpression(): void
    {
        $path = TMP . 'tests/docs/test.html';
        $this->filesystem->copy(TESTS . 'data/html/test.html', $path, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Couldn't process XPath expression `expression`.");

        $document = $this
            ->getMockBuilder(Document::class)
            ->setConstructorArgs([$path])
            ->onlyMethods(['processXPathExpression'])
            ->getMock();

        $document
            ->expects($this->once())
            ->method('processXPathExpression')
            ->willReturn(null);

        $document->query('expression');
    }

    public function testQueryInvalidExpression(): void
    {
        $path = TMP . 'tests/docs/test.html';
        $this->filesystem->copy(TESTS . 'data/html/test.html', $path, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Couldn't evaluate XPath expression `[invalid]expression`.");

        $document = new Document($path);
        $document->query('[invalid]expression');
    }
}
