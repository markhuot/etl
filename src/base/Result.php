<?php

namespace markhuot\etl\base;

use markhuot\etl\Etl;

class Result
{
    public function __construct(
        public Etl $etl,
        public array $errors = [],
    ) {
    }
}
