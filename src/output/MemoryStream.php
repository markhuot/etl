<?php

namespace markhuot\etl\output;

class MemoryStream implements StreamInterface
{
    protected $messages = [];

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
