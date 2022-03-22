<?php
declare(strict_types=1);

namespace App\TestSuite\Stub;

use Cake\Console\ConsoleOutput as BaseConsoleOutput;

class ConsoleOutput extends BaseConsoleOutput
{
    /**
     * Buffered messages.
     *
     * @var array<string>
     */
    protected $_out = [];

    /**
     * Write output to the buffer.
     *
     * @param array<string>|string $message A string or an array of strings to output
     * @param int $newlines Number of newlines to append
     * @return int
     */
    public function write($message, int $newlines = 1): int
    {
        foreach ((array)$message as $line) {
            $this->_out[] = $line;
        }

        return 0;
    }

    /**
     * Get the buffered output.
     *
     * @return array<string>
     */
    public function messages(): array
    {
        return $this->_out;
    }
}
