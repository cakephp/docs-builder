<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\PopulateIndexCommand;
use App\Index\Document;
use App\Index\ErrorResponseException;
use App\Index\Manager;
use App\TestSuite\Stub\ConsoleOutput;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PopulateIndexCommandTest extends TestCase
{
    /**
     * @var string
     */
    protected $comparisons;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var \App\Index\Manager
     */
    protected $manager;

    /**
     * @var \App\TestSuite\Stub\ConsoleOutput
     */
    protected $out;

    /**
     * @var \App\TestSuite\Stub\ConsoleOutput
     */
    protected $err;

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected $io;

    /**
     * @var \App\Command\PopulateIndexCommand
     */
    protected $command;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->comparisons = __DIR__ . '/../../comparisons/Command/' . namespaceSplit(self::class)[1] . '/';

        $this->filesystem = new Filesystem();
        $this->filesystem->remove(TMP . 'tests');

        $this->out = new ConsoleOutput();
        $this->err = new ConsoleOutput();
        $this->io = new ConsoleIo($this->out, $this->err);

        $this->command = new PopulateIndexCommand();

        $this->manager = new Manager('http://127.0.0.1:9200');

        $this->deleteTestIndices();
    }

    /**
     * Deletes all test indices, putting the Elasticsearch node in a
     * clean state.
     *
     * @return void
     */
    protected function deleteTestIndices(): void
    {
        $indices = [];
        $currentIndex = $this->manager->getAliasTargetIndex('cake-docs-test-1-1-en');
        if ($currentIndex) {
            $indices[] = $currentIndex;
        }

        $orphanedIndices = $this->manager->getOrphanedIndicesForAlias('cake-docs-test-1-1-en');
        foreach (array_merge($indices, $orphanedIndices) as $index) {
            $this->manager->deleteIndex($index);
        }
    }

    /**
     * Asserts the messages using regular expressions.
     *
     * @param string[] $expected The expected message regex patterns.
     * @param string[] $actual The actual messages.
     * @return void
     */
    protected function assertMessagesRegExp(array $expected, array $actual): void
    {
        foreach ($expected as $index => $pattern) {
            $this->assertArrayHasKey($index, $actual);
            $this->assertRegExp($pattern, $actual[$index]);
        }

        $this->assertCount(count($expected), $actual);
    }

    public function testMissingSourceOption(): void
    {
        $code = $this->command->run([
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertEmpty($this->out->messages());
        $this->assertSame(
            ['Error: Missing required option. The `source` option is required and has no default value.'],
            $this->err->messages()
        );
    }

    public function testMissingLangOption(): void
    {
        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--url-prefix', '/1.1',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertEmpty($this->out->messages());
        $this->assertSame(
            ['Error: Missing required option. The `lang` option is required and has no default value.'],
            $this->err->messages()
        );
    }

    public function testMissingUrlPrefixOption(): void
    {
        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertEmpty($this->out->messages());
        $this->assertSame(
            ['Error: Missing required option. The `url-prefix` option is required and has no default value.'],
            $this->err->messages()
        );
    }

    public function testNonExistentSourcePath(): void
    {
        $code = $this->command->run([
            '--source', TMP . 'non-existent',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>The source path `.+?/non-existent` could not be found\.\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testMissingTocFile(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->remove(TMP . 'tests/docs/contents.html');

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>The TOC file `.+?/tests/docs/contents\.html` could not be found.\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testInvalidTocFile(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->dumpFile(TMP . 'tests/docs/contents.html', '');

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Loading `.+?/tests/docs/contents\.html` failed: Document is empty\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testUnsupportedInternalReferences(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/unsupported-internal-references.html',
            TMP . 'tests/docs/contents.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>No internal references found in `.+?/tests/docs/contents\.html`\.\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testNonExistentInternalReference(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/non-existent-internal-reference.html',
            TMP . 'tests/docs/contents.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>The internal reference `.+?/tests/docs/non-existent\.html` ' .
                    'could not be resolved\.\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testBasePathIncompatibleInternalReference(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/base-path-incompatible-internal-reference.html',
            TMP . 'tests/docs/contents.html',
            true
        );
        $this->filesystem->touch(TMP . 'tests/base-path-incompatible.html');

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>The internal reference `\.\./base-path-incompatible\.html` ' .
                    'points to outside the content base path `.+?/tests/docs`\.\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testNoInternalReferences(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/no-internal-references.html',
            TMP . 'tests/docs/contents.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>No internal references found in `.+?/tests/docs/contents\.html`\.\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testInvalidContentFile(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->dumpFile(TMP . 'tests/docs/test.html', '');

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Loading `.+?/tests/docs/test\.html` failed: Document is empty\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testUnsupportedExternalReferences(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/unsupported-external-references.html',
            TMP . 'tests/docs/contents.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
            ],
            $this->err->messages()
        );
    }

    public function testNoSidebarNavigation(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/no-sidebar-navigation.html',
            TMP . 'tests/docs/test.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>No sidebar navigation found in `.+?/tests/docs/test.html`\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testNoActiveTocHierarchy(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/no-active-toc-hierarchy.html',
            TMP . 'tests/docs/test.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>No active TOC hierarchy found in `.+?/tests/docs/test\.html`\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testNoDocumentBody(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(TESTS . 'data/scenarios/no-document-body.html', TMP . 'tests/docs/test.html', true);

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>No document body found in `.+?/tests/docs/test\.html`\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testNoRootSection(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(TESTS . 'data/scenarios/no-root-section.html', TMP . 'tests/docs/test.html', true);

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>No root section found in `.+?/tests/docs/test\.html`\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testMissingSectionAnchor(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/missing-section-anchor.html',
            TMP . 'tests/docs/test.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Missing section anchor in `.+?/tests/docs/test\.html`\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testMissingTitleNode(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(TESTS . 'data/scenarios/missing-title-node.html', TMP . 'tests/docs/test.html', true);

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Missing title node in `#missing-title-node` section\.\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testMissingSectionTitle(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/missing-section-title.html',
            TMP . 'tests/docs/test.html',
            true
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Missing section title in `#missing-section-title` section\.\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testInvalidHost(): void
    {
        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://invalid',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertSame(
            [
                'Deleting orphaned indices for index alias `cake-docs-test-1-1-en`.',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<error\>cURL Error \(6\) Could not resolve host: invalid\</error\>@',
                '@\<error\>#0@',
            ],
            $this->err->messages()
        );
    }

    public function testCannotCreateBuildIndex(): void
    {
        $manager = $this
            ->getMockBuilder(Manager::class)
            ->setConstructorArgs(['http://127.0.0.1:9200'])
            ->onlyMethods(['createIndex'])
            ->getMock();

        $manager
            ->expects($this->once())
            ->method('createIndex')
            ->willThrowException(new ErrorResponseException('[400] foo bar baz', 400));

        $command = $this
            ->getMockBuilder(PopulateIndexCommand::class)
            ->onlyMethods(['getIndexManager'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('getIndexManager')
            ->willReturn($manager);

        $code = $command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>The `cake-docs-test-1-1-en-\d+` ' .
                    'index could not be created: \[400\] foo bar baz\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testCannotStoreInternalReferenceDocument(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $manager = $this
            ->getMockBuilder(Manager::class)
            ->setConstructorArgs(['http://127.0.0.1:9200'])
            ->onlyMethods(['storeDocument'])
            ->getMock();

        $manager
            ->expects($this->atLeastOnce())
            ->method('storeDocument')
            ->willReturnCallback(function (Document $document) {
                if ($document->getData()['type'] === Document::TYPE_INTERNAL) {
                    throw new ErrorResponseException('[400] foo bar baz', 400);
                }

                return true;
            });

        $command = $this
            ->getMockBuilder(PopulateIndexCommand::class)
            ->onlyMethods(['getIndexManager'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('getIndexManager')
            ->willReturn($manager);

        $code = $command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Could not store the document for ' .
                    '`/1.1/en/test\.html#namespace-Foo\\\\Bar`: \[400\] foo bar baz\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testCannotStoreExternalReferenceDocument(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $manager = $this
            ->getMockBuilder(Manager::class)
            ->setConstructorArgs(['http://127.0.0.1:9200'])
            ->onlyMethods(['storeDocument'])
            ->getMock();

        $manager
            ->expects($this->atLeastOnce())
            ->method('storeDocument')
            ->willReturnCallback(function (Document $document) {
                if ($document->getData()['type'] === Document::TYPE_EXTERNAL) {
                    throw new ErrorResponseException('[400] foo bar baz', 400);
                }

                return true;
            });

        $command = $this
            ->getMockBuilder(PopulateIndexCommand::class)
            ->onlyMethods(['getIndexManager'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('getIndexManager')
            ->willReturn($manager);

        $code = $command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>Could not store the document for ' .
                    '`https://example.com/foo`: \[400\] foo bar baz\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testCannotSetAlias(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $manager = $this
            ->getMockBuilder(Manager::class)
            ->setConstructorArgs(['http://127.0.0.1:9200'])
            ->onlyMethods(['setAlias'])
            ->getMock();

        $manager
            ->expects($this->once())
            ->method('setAlias')
            ->willThrowException(new ErrorResponseException('[400] foo bar baz', 400));

        $command = $this
            ->getMockBuilder(PopulateIndexCommand::class)
            ->onlyMethods(['getIndexManager'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('getIndexManager')
            ->willReturn($manager);

        $code = $command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_ERROR, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<error\>The alias `cake-docs-test-1-1-en` could not be set to point to ' .
                    '`cake-docs-test-1-1-en-\d+`: \[400\] foo bar baz\</error\>@',
            ],
            $this->err->messages()
        );
    }

    public function testCannotDeleteOldBuildIndex(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12345',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->setAlias('cake-docs-test-1-1-en-12345', 'cake-docs-test-1-1-en');

        $manager = $this
            ->getMockBuilder(Manager::class)
            ->setConstructorArgs(['http://127.0.0.1:9200'])
            ->onlyMethods(['deleteIndex'])
            ->getMock();

        $manager
            ->expects($this->atLeastOnce())
            ->method('deleteIndex')
            ->willReturnCallback(function (string $index) {
                if ($index === 'cake-docs-test-1-1-en-12345') {
                    throw new ErrorResponseException('[400] foo bar baz', 400);
                }
            });

        $command = $this
            ->getMockBuilder(PopulateIndexCommand::class)
            ->onlyMethods(['getIndexManager'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('getIndexManager')
            ->willReturn($manager);

        $code = $command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@Alias `cake-docs-test-1-1-en` is currently pointing at `cake-docs-test-1-1-en-12345`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@Deleting old build index `cake-docs-test-1-1-en-12345`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en-12345` ' .
                    'could not be deleted: \[400\] foo bar baz\</warning\>@',
            ],
            $this->err->messages()
        );
    }

    public function testCannotDeleteOrphanedIndex(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12344',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12345',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->setAlias('cake-docs-test-1-1-en-12345', 'cake-docs-test-1-1-en');

        $manager = $this
            ->getMockBuilder(Manager::class)
            ->setConstructorArgs(['http://127.0.0.1:9200'])
            ->onlyMethods(['deleteIndex'])
            ->getMock();

        $manager
            ->expects($this->atLeastOnce())
            ->method('deleteIndex')
            ->willReturnCallback(function (string $index) {
                if ($index === 'cake-docs-test-1-1-en-12344') {
                    throw new ErrorResponseException('[400] foo bar baz', 400);
                }
            });

        $command = $this
            ->getMockBuilder(PopulateIndexCommand::class)
            ->onlyMethods(['getIndexManager'])
            ->getMock();

        $command
            ->expects($this->once())
            ->method('getIndexManager')
            ->willReturn($manager);

        $code = $command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@Deleting orphaned index `cake-docs-test-1-1-en-12344`\.@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@Alias `cake-docs-test-1-1-en` is currently pointing at `cake-docs-test-1-1-en-12345`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@Deleting old build index `cake-docs-test-1-1-en-12345`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en-12344` ' .
                    'could not be deleted: \[400\] foo bar baz\</warning\>@',
            ],
            $this->err->messages()
        );
    }

    public function testOrphanedIndices(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12343',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12344',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12345',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->setAlias('cake-docs-test-1-1-en-12345', 'cake-docs-test-1-1-en');

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@Deleting orphaned index `cake-docs-test-1-1-en-12343`\.@',
                '@Deleting orphaned index `cake-docs-test-1-1-en-12344`\.@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@Alias `cake-docs-test-1-1-en` is currently pointing at `cake-docs-test-1-1-en-12345`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@Deleting old build index `cake-docs-test-1-1-en-12345`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertEmpty($this->err->messages());

        $this->assertRegExp(
            '/^cake-docs-test-1-1-en-\d+$/',
            (string)$this->manager->getAliasTargetIndex('cake-docs-test-1-1-en')
        );
        $this->assertEmpty($this->manager->getOrphanedIndicesForAlias('cake-docs-test-1-1-en'));

        $this->manager->refreshIndex('cake-docs-test-1-1-en');
        $expected = require $this->comparisons . 'success.php';
        $documents = array_map(
            function (Document $document) {
                return $document->toArray();
            },
            $this->manager->getAllDocuments('cake-docs-test-1-1-en')
        );
        $this->assertSame($expected, $documents);
    }

    public function testSkipExcludedFiles(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(
            TESTS . 'data/scenarios/excluded-internal-references.html',
            TMP . 'tests/docs/contents.html',
            true
        );
        $this->filesystem->touch([
            TMP . 'tests/docs/404.html',
            TMP . 'tests/docs/epub-contents.html',
            TMP . 'tests/docs/pdf-contents.html',
            TMP . 'tests/docs/genindex.html',
            TMP . 'tests/docs/php-modindex.html',
            TMP . 'tests/docs/search.html',
            TMP . 'tests/docs/topics.html',
            TMP . 'tests/docs/glossary.html',
        ]);

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@\<info\>Skipping internal reference ' .
                    '`404\.html` because of exclusion rule `/\^404\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`contents\.html` because of exclusion rule `/\^contents\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`epub-contents\.html` because of exclusion rule `/\^epub-contents\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`pdf-contents\.html` because of exclusion rule `/\^pdf-contents\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`genindex\.html` because of exclusion rule `/\^genindex\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`php-modindex\.html` because of exclusion rule `/\^php-modindex\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`search\.html` because of exclusion rule `/\^search\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`topics\.html` because of exclusion rule `/\^topics\\\.html\$/`\.\</info\>@',
                '@\<info\>Skipping internal reference ' .
                    '`glossary\.html` because of exclusion rule `/\^glossary\\\.html\$/`\.\</info\>@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
            ],
            $this->err->messages()
        );

        $this->assertRegExp(
            '/^cake-docs-test-1-1-en-\d+$/',
            (string)$this->manager->getAliasTargetIndex('cake-docs-test-1-1-en')
        );
        $this->assertEmpty($this->manager->getOrphanedIndicesForAlias('cake-docs-test-1-1-en'));

        $this->manager->refreshIndex('cake-docs-test-1-1-en');
        $expected = require $this->comparisons . 'skip-excluded-files.php';
        $documents = array_map(
            function (Document $document) {
                return $document->toArray();
            },
            $this->manager->getAllDocuments('cake-docs-test-1-1-en')
        );
        $this->assertSame($expected, $documents);
    }

    public function testNoSectionContent(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);
        $this->filesystem->copy(TESTS . 'data/scenarios/no-section-content.html', TMP . 'tests/docs/test.html', true);

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                'the previous build might have failed: \[404\] no such index\</warning\>@',
                '@\<warning\>No sections found in `.+?/tests/docs/test\.html`\. ' .
                'This might be a page that contains only a TOC tree\.\</warning\>@',
            ],
            $this->err->messages()
        );

        $this->assertRegExp(
            '/^cake-docs-test-1-1-en-\d+$/',
            (string)$this->manager->getAliasTargetIndex('cake-docs-test-1-1-en')
        );
        $this->assertEmpty($this->manager->getOrphanedIndicesForAlias('cake-docs-test-1-1-en'));

        $this->manager->refreshIndex('cake-docs-test-1-1-en');
        $expected = require $this->comparisons . 'no-section-content.php';
        $documents = array_map(
            function (Document $document) {
                return $document->toArray();
            },
            $this->manager->getAllDocuments('cake-docs-test-1-1-en')
        );
        $this->assertSame($expected, $documents);
    }

    public function testExistingNonAliasedIndex(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $this->manager->createIndex(
            'cake-docs-test-1-1-en',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertEmpty($this->err->messages());

        $this->assertRegExp(
            '/^cake-docs-test-1-1-en-\d+$/',
            (string)$this->manager->getAliasTargetIndex('cake-docs-test-1-1-en')
        );

        $this->manager->refreshIndex('cake-docs-test-1-1-en');
        $expected = require $this->comparisons . 'success.php';
        $documents = array_map(
            function (Document $document) {
                return $document->toArray();
            },
            $this->manager->getAllDocuments('cake-docs-test-1-1-en')
        );
        $this->assertSame($expected, $documents);
    }

    public function testExistingAliasedIndex(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $this->manager->createIndex(
            'cake-docs-test-1-1-en-12345',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $this->manager->setAlias('cake-docs-test-1-1-en-12345', 'cake-docs-test-1-1-en');

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@Alias `cake-docs-test-1-1-en` is currently pointing at `cake-docs-test-1-1-en-12345`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@Deleting old build index `cake-docs-test-1-1-en-12345`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertEmpty($this->err->messages());

        $result = (string)$this->manager->getAliasTargetIndex('cake-docs-test-1-1-en');
        $this->assertNotSame('cake-docs-test-1-1-en-12345', $result);
        $this->assertRegExp('/^cake-docs-test-1-1-en-\d+$/', $result);
        $this->assertEmpty($this->manager->getOrphanedIndicesForAlias('cake-docs-test-1-1-en'));

        $this->manager->refreshIndex('cake-docs-test-1-1-en');
        $expected = require $this->comparisons . 'success.php';
        $documents = array_map(
            function (Document $document) {
                return $document->toArray();
            },
            $this->manager->getAllDocuments('cake-docs-test-1-1-en')
        );
        $this->assertSame($expected, $documents);
    }

    public function testNoExistingIndices(): void
    {
        $this->filesystem->mirror(TESTS . 'data/html', TMP . 'tests/docs', null, ['override' => true]);

        $code = $this->command->run([
            '--source', TMP . 'tests/docs',
            '--lang', 'en',
            '--url-prefix', '/1.1',
            '--index-prefix', 'cake-docs-test',
            '--host', 'http://127.0.0.1:9200',
        ], $this->io);

        $this->assertSame(CommandInterface::CODE_SUCCESS, $code);
        $this->assertMessagesRegExp(
            [
                '@Deleting orphaned indices for index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No orphaned indices found\.\</info\>@',
                '@Checking index alias `cake-docs-test-1-1-en`\.@',
                '@\<info\>No index alias exists. Migrating to index aliases\.\</info\>@',
                '@Deleting old, non-aliased index `cake-docs-test-1-1-en`\.@',
                '@Creating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Populating build index `cake-docs-test-1-1-en-\d+`\.@',
                '@Storing document for `/1.1/en/test\.html#namespace-Foo\\\\Bar`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-2-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/test\.html#level-1-subsection-2-title`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#nested`\.@',
                '@Storing document for `/1.1/en/test/nested\.html#level-1-subsection-1-title`\.@',
                '@Storing document for `/1.1/en/more\.html#more`\.@',
                '@Storing document for `/1.1/en/appendices\.html#appendices`\.@',
                '@Storing document for `/1.1/en/appendices/low-priority\.html#low-priority`\.@',
                '@Storing document for `https://example\.com/foo`\.@',
                '@Storing document for `https://example\.com/bar`\.@',
                '@Storing document for `https://example\.com/baz`\.@',
                '@Setting alias `cake-docs-test-1-1-en` to point to `cake-docs-test-1-1-en-\d+`\.@',
                '@\<success\>Index update complete\.\</success\>@',
            ],
            $this->out->messages()
        );
        $this->assertMessagesRegExp(
            [
                '@\<warning\>The index `cake-docs-test-1-1-en` could not be deleted, ' .
                    'the previous build might have failed: \[404\] no such index\</warning\>@',
            ],
            $this->err->messages()
        );

        $this->assertRegExp(
            '/^cake-docs-test-1-1-en-\d+$/',
            (string)$this->manager->getAliasTargetIndex('cake-docs-test-1-1-en')
        );
        $this->assertEmpty($this->manager->getOrphanedIndicesForAlias('cake-docs-test-1-1-en'));

        $this->manager->refreshIndex('cake-docs-test-1-1-en');
        $expected = require $this->comparisons . 'success.php';
        $documents = array_map(
            function (Document $document) {
                return $document->toArray();
            },
            $this->manager->getAllDocuments('cake-docs-test-1-1-en')
        );
        $this->assertSame($expected, $documents);
    }
}
