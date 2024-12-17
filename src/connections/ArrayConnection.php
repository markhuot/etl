<?php

namespace markhuot\etl\connections;

use Generator;
use markhuot\etl\base\DestinationConnectionInterface;
use markhuot\etl\base\SourceConnectionInterface;
use markhuot\etl\base\Frame;

class ArrayConnection extends Connection implements SourceConnectionInterface, DestinationConnectionInterface
{
    public function __construct(
        public array $array = [],
    ) {
    }

    public function walk(): Generator
    {
        $frames = [];

        foreach ($this->array as $index => $data) {
            $frames[] = new Frame(
                $data,
                sourceKey: $index,
            );
        }

        //$this->trigger('batch', $frames);

        yield $frames;
    }

    public function prepareFrame(Frame $frame): Frame
    {
        return (clone $frame)->setData([]);
    }

    public function upsertFrame(Frame $frame): void
    {
        if ($frame->sourceKey) {
            $this->array[$frame->sourceKey] = $frame->data;
        }
        else {
            $this->array[] = $frame->data;
        }

        $frame->lastError = null;
        $frame->lastImport = new \DateTime();

        $this->trigger('upsert', [$frame]);
    }

    public function close(): void
    {
        // no-op here since frames are inserted immediately there's no file pointer
        // to close.
    }
}
