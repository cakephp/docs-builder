<?php
declare(strict_types=1);

namespace App\Test\TestCase\Index;

use App\Index\ErrorResponseException;
use App\Index\Manager;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\TestCase;

class MangerTest extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        Client::clearMockResponses();
    }

    public function testAliasTargetIndexOrder(): void
    {
        $manager = new Manager('http://127.0.0.1:9200');

        $manager->createIndex(
            'test-12344',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $manager->createIndex(
            'test-12345',
            ['number_of_shards' => 1],
            ['test' => ['type' => 'keyword']]
        );
        $manager->setAlias('test-12344', 'test-alias');
        $manager->setAlias('test-12345', 'test-alias');

        $index = $manager->getAliasTargetIndex('test-alias');
        $this->assertSame('test-12345', $index);

        $manager->deleteIndex('test-12344');
        $manager->deleteIndex('test-12345');
    }

    public function testSimpleErrorResponse(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('[400] message');

        $stream = new Stream('php://memory', 'wb+');
        $stream->write('{"error":"message","status":400}');

        Client::addMockResponse(
            'GET',
            'http://127.0.0.1:9200/_alias/alias',
            (new Response())->withStatus(400)->withBody($stream)
        );

        $manager = new Manager('http://127.0.0.1:9200');
        $manager->getAliasTargetIndex('alias');
    }

    public function testComplexErrorResponse(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('[400] message');

        $stream = new Stream('php://memory', 'wb+');
        $stream->write('{"error":{"reason":"message"},"status":400}');

        Client::addMockResponse(
            'GET',
            'http://127.0.0.1:9200/_alias/alias',
            (new Response())->withStatus(400)->withBody($stream)
        );

        $manager = new Manager('http://127.0.0.1:9200');
        $manager->getAliasTargetIndex('alias');
    }
}
