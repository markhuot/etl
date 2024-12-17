<?php

namespace markhuot\etl\base;

use stdClass;
use Throwable;

interface AuditorInterface
{
    public function trackFrame (Frame $frame): Frame;

    public function trackSourceKey(string $key): void;

    public function trackFrames(array $frames): void;

    public function trackErrorForFrame(Frame $frame, Throwable $throwable): void;

    public function getImportStats(): array;

    public function getStatusForKey(string $key): ?stdClass;

    public function getTotals(): array;
}
