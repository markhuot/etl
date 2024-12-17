<?php

namespace markhuot\etl\output;

interface StreamInterface
{
    public function info(string $message, string $verbosity='v'): void;
    public function error(string $message, string $verbosity='v'): void;
    public function close(): void;
}
