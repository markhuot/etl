<?php

namespace markhuot\etl\base;

use Generator;

interface TransformerInterface
{
    /**
     * @param Frame<mixed> $source
     */
    public function canTransform(Frame $source): bool;

    /**
     * @param Frame<mixed> $source
     * @param Frame<mixed> $destination
     */
    public function transform(Frame $source, Frame $destination): void;
}
