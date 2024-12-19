<?php

namespace markhuot\etl\connections;

use markhuot\etl\base\DestinationConnectionInterface;
use markhuot\etl\base\Frame;

abstract class BufferingConnection extends Connection implements DestinationConnectionInterface
{
    protected int $batchSize = 100;

    /**
     * @var array<Frame<mixed>>
     */
    protected array $frameBuffer = [];

    /**
     * @param Frame<mixed> $frame
     */
    public function upsertFrame(Frame $frame): void
    {
        $this->frameBuffer[] = $frame;

        if (count($this->frameBuffer) < $this->batchSize) {
            return;
        }

        $this->tryToSendBuffer();
    }

    public function close(): void
    {
        if (empty($this->frameBuffer)) {
            return;
        }

        $this->tryToSendBuffer();
    }

    public function tryToSendBuffer(): void
    {
        try {
            $this->sendBuffer();
            $this->trigger('upsert', $this->frameBuffer);
            $this->frameBuffer = [];
        }
        catch (\Throwable $e) {
            $this->trigger('error', $this->frameBuffer, $e);
            $this->frameBuffer = [];
        }
    }

    abstract function sendBuffer(): void;
}
