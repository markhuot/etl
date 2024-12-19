<?php

namespace markhuot\etl\base;

use markhuot\etl\Voyage;

class Result
{
    /**
     * @param array<mixed> $errors
     */
    public function __construct(
        public Voyage $etl,
        public array $errors = [],
    ) {
    }
}
