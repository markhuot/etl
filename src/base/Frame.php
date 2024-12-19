<?php

namespace markhuot\etl\base;

use DateTime;
use http\Exception\RuntimeException;
use markhuot\etl\phases\DefaultPhase;
use Throwable;

/**
 * @template T
 */
class Frame
{
    /**
     * @param T $data
     */
    public function __construct(
        public mixed $data,
        public string $phase=DefaultPhase::class,
        public string $collection='default',
        public string|int|null $sourceKey=null,
        public string|int|null $destinationKey=null,
        public string|null $checksum=null,
        public Throwable|null $exception=null,
        public DateTime|null $lastError=null,
        public DateTime|null $lastImport=null,
    ) {
    }

    /**
     * @return Frame<T>
     */
    public function setData(mixed $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function matchesChecksum(): bool
    {
        if (empty($this->checksum)) {
            return false;
        }

        return $this->checksum === $this->getDerivedChecksum();
    }

    public function getDerivedChecksum(): string
    {
        $json = json_encode($this->data);
        if (! $json) {
            throw new RuntimeException('Could not create json from frame data. Frame ' . $this->phase . ' ' . $this->collection . ' ' . $this->sourceKey);
        }

        return md5($json);
    }
}
