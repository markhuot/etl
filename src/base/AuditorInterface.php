<?php

namespace markhuot\etl\base;

use stdClass;
use Throwable;

interface AuditorInterface
{
    public function trackFrames(array $frames): void;

    public function trackErrorForFrames(array $frames, Throwable $throwable): void;

    public function getImportStats(): array;

    public function getStatusForKey(string $phase, string $collection, string|int $sourceKey): ?stdClass;
}
