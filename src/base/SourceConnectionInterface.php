<?php

namespace markhuot\etl\base;

use Generator;

interface SourceConnectionInterface extends ConnectionInterface
{
    /**
     * @return Generator<array<Frame>>
     */
    public function walk(): Generator;
}
