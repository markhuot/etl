<?php

namespace markhuot\etl\base;

use Generator;

interface TransformerInterface
{
    public function canTransform(Frame $source): bool;

    public function transform(Frame $source, Frame $destination): void;
}
