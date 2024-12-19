<?php

namespace markhuot\etl\base;

use DateTime;
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
        public string $phase='default',
        public string $collection='default',
        public string|null $sourceKey=null,
        public string|null $destinationKey=null,
        public string|null $checksum=null,
        public Throwable|null $exception=null,
        public DateTime|null $lastError=null,
        public DateTime|null $lastImport=null,
    ) {
    }

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
        return md5(json_encode($this->data));
    }
}
