<?php

namespace markhuot\etl\output;

class MemoryStream implements StreamInterface
{
    /**
     * @var array<array{0: string, 1: string, 2: string}>
     */
    protected array $messages = [];

    public function info(string $message, string $verbosity = 'v'): void
    {
        $this->messages[] = ['info', $message, $verbosity];
    }

    public function error(string $message, string $verbosity = 'v'): void
    {
        $this->messages[] = ['error', $message, $verbosity];
    }

    public function close(): void
    {
        // no-op
    }
}
