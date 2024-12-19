<?php

namespace markhuot\etl\base;

use stdClass;
use Throwable;

interface AuditorInterface
{
    /**
     * @param array<Frame<mixed>> $frames
     */
    public function fetchFrames(array $frames): void;

    /**
     * @param array<Frame<mixed>> $frames
     */
    public function trackFrames(array $frames): void;

    /**
     * @param array<Frame<mixed>> $frames
     */
    public function trackErrorForFrames(array $frames, Throwable $throwable): void;

    /**
     * @return array<string, array<string, int>>
     */
    public function getImportStats(): array;

    public function getStatusForKey(string $phase, string $collection, string|int $sourceKey): ?stdClass;
}
