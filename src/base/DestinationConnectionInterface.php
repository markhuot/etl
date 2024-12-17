<?php

namespace markhuot\etl\base;

use Generator;

interface DestinationConnectionInterface extends ConnectionInterface
{
    public function prepareFrame(Frame $frame): Frame;

    public function upsertFrame(Frame $frame): void;

    public function close(): void;
}
