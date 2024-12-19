<?php

namespace markhuot\etl\connections;

use Generator;
use markhuot\etl\base\DestinationConnectionInterface;
use markhuot\etl\base\SourceConnectionInterface;
use markhuot\etl\base\Frame;

class ArrayConnection extends Connection implements SourceConnectionInterface, DestinationConnectionInterface
{
    /**
     * @param array<mixed> $array
     */
    public function __construct(
        public array $array = [],
    ) {
    }

    /**
     * @return Generator<array<Frame<mixed>>>
     */
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

    /**
     * @param Frame<mixed> $frame
     * @return Frame<mixed>
     */
    public function prepareFrame(Frame $frame): Frame
    {
        return (clone $frame)->setData([]);
    }

    /**
     * @param Frame<mixed> $frame
     */
    public function upsertFrame(Frame $frame): void
    {
        if ($frame->sourceKey) {
            $this->array[$frame->sourceKey] = $frame->data;
            $destinationKey = $frame->sourceKey;
        }
        else {
            $this->array[] = $frame->data;
            $destinationKey = count($this->array) - 1;
        }

        $frame->destinationKey = $destinationKey;
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
