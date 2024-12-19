<?php

namespace markhuot\etl\base;

use markhuot\etl\Voyage;

class Result
{
    public function __construct(
        public Voyage $etl,
        public array $errors = [],
    ) {
    }
}
