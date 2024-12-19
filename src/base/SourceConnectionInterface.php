<?php

namespace markhuot\etl\base;

use Generator;

interface SourceConnectionInterface extends ConnectionInterface
{
    /**
     * @return Generator<array<Frame<mixed>>>
     */
    public function walk(): Generator;
}
