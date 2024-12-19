<?php

namespace markhuot\etl\base;

use Generator;

interface DestinationConnectionInterface extends ConnectionInterface
{
    /**
     * @param Frame<mixed> $frame
     * @return Frame<mixed>
     */
    public function prepareFrame(Frame $frame): Frame;

    /**
     * @param Frame<mixed> $frame
     */
    public function upsertFrame(Frame $frame): void;

    public function close(): void;
}
