<?php

namespace markhuot\etl;

use markhuot\etl\auditors\Sqlite;
use markhuot\etl\base\AuditorInterface;
use markhuot\etl\base\DestinationConnectionInterface;
use markhuot\etl\base\SourceConnectionInterface;
use markhuot\etl\base\Frame;
use markhuot\etl\base\Result;
use markhuot\etl\base\Transformer;
use markhuot\etl\base\TransformerInterface;
use markhuot\etl\exceptions\TransformerException;
use markhuot\etl\output\PhpStreamWrapper;
use markhuot\etl\output\StreamInterface;
use markhuot\etl\phases\DefaultPhase;
use Throwable;

class Etl
{
    /** @var array<TransformerInterface> */
    public array $transformers = [];

    public function __construct(
        protected ?SourceConnectionInterface $source=null,
        protected ?DestinationConnectionInterface $destination=null,
        protected ?AuditorInterface $auditor=null,
        protected bool $devMode = false,
        protected ?Result $result=null,
        protected ?StreamInterface $stream=null,
    ) {
        $this->result ??= new Result($this);
        $this->stream ??= new PhpStreamWrapper;
    }

    public function from(SourceConnectionInterface $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function to(DestinationConnectionInterface $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function auditor(AuditorInterface $auditor)
    {
        $this->auditor = $auditor;

        return $this;
    }

    public function devMode(bool $devMode): self
    {
        $this->devMode = $devMode;

        return $this;
    }

    public function addTransformer(TransformerInterface $transformer): self
    {
        $this->transformers[] = $transformer;

        return $this;
    }

    public function getAuditor(): AuditorInterface
    {
        return $this->auditor;
    }

    public function start(
        $auditOnly=false,
        string $phase=DefaultPhase::class,
    ): Result {
        $this->destination->on('upsert', function (array $frames) {
            $this->auditor?->trackFrames($frames);
            $this->stream->info('Sent ' . count($frames) . ' frames to ' . get_class($this->destination));
        });
        $this->destination->on('error', function (array $frames, Throwable $e) {
            $this->auditor->trackErrorForFrames($frames, $e);
            $this->stream->error('Error sending frames to ' . get_class($this->destination) . ': ' . get_class($e) . ': ' . $e->getMessage());
            $this->stream->error(json_encode($frames));
        });

        foreach ($this->source->walk() as $batch) {
            $this->auditor?->fetchFrames($batch);

            if ($auditOnly) {
                continue;
            }

            foreach ($batch as $sourceFrame) {
                $destinationFrame = $this->destination->prepareFrame($sourceFrame);

                if ($this->transform($sourceFrame, $destinationFrame, $phase)) {
                    if ($destinationFrame->matchesChecksum()) {
                        $this->stream->info('⇢ Skipping frame [' . $sourceFrame->collection . ':' . $sourceFrame->sourceKey . '] with no changes', 'vvv');
                    }
                    else {
                        $this->stream->info('↑ Upserting frame [' . $sourceFrame->collection . ':' . $sourceFrame->sourceKey . ']', 'vvv');
                        $this->destination->upsertFrame($destinationFrame);
                    }
                }
            }

        }

        $this->destination->close();

        return $this->result;
    }

    public function transform(Frame $source, Frame $destination, string $phase): bool
    {
        try {
            foreach ($this->transformers as $transformer) {
                $reflect = new \ReflectionClass($transformer);
                if (! $reflect->implementsInterface($phase)) {
                    continue;
                }

                if ($transformer->canTransform($source)) {
                    $transformer->transform($source, $destination);
                }
            }

            return true;
        }
        catch (Throwable $throwable) {
            $this->auditor->trackErrorForFrame($destination, $throwable);
            $this->result->errors[] = $throwable;

            if ($this->devMode) {
                throw $throwable;
            }

            return false;
        }
    }
}
