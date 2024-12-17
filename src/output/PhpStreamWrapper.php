<?php

namespace markhuot\etl\output;

class PhpStreamWrapper implements StreamInterface
{
    protected int $verbosity = 1;

    public function __construct(
        string $verbosity='v',
    ) {
        $this->verbosity = strlen($verbosity);
    }

    public function info(string $message, string $verbosity='v'): void
    {
        if (strlen($verbosity) > $this->verbosity) {
            return;
        }

        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function error(string $message, string $verbosity='v'): void
    {
        if (strlen($verbosity) > $this->verbosity) {
            return;
        }

        fwrite(STDERR, $message . PHP_EOL);
    }

    public function close(): void
    {
        // no-op. No need to close stdin or stderr streams in PHP
    }
}
