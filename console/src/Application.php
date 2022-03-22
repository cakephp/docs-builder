<?php
declare(strict_types=1);

namespace App;

use App\Command\PopulateIndexCommand;
use Cake\Console\CommandCollection;
use Cake\Core\ConsoleApplicationInterface;

class Application implements ConsoleApplicationInterface
{
    /**
     * @inheritDoc
     */
    public function bootstrap(): void
    {
        // noop
    }

    /**
     * @inheritDoc
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('index:populate', PopulateIndexCommand::class);

        return $commands;
    }
}
