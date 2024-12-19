<?php

namespace markhuot\etl\base;

interface ConnectionInterface
{
    public function on(string $event, callable $listener): static;

    public function trigger(string $event, mixed ...$args): void;
}
