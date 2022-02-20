<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Application;
use App\Command\PopulateIndexCommand;
use Cake\Console\CommandCollection;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testBootstrap(): void
    {
        $application = new Application();
        $application->bootstrap();

        // https://github.com/sebastianbergmann/phpunit/issues/3016
        $this->assertTrue(true);
    }

    public function testConsole(): void
    {
        $collection = new CommandCollection();

        $application = new Application();
        $collection = $application->console($collection);

        $this->assertSame(1, $collection->count());
        $this->assertSame(PopulateIndexCommand::class, $collection->get('index:populate'));
    }
}
