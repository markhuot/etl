<?php

namespace markhuot\etl\connections;

abstract class Connection
{
    protected array $listeners = [];

    public function on(string $event, callable $listener): static
    {
        $this->listeners[$event][] = $listener;

        return $this;
    }

    public function trigger(string $event, ...$args): void
    {
        $listeners = $this->listeners[$event] ?? [];

        foreach ($listeners as $listener) {
            $listener(...$args);
        }
    }

}
