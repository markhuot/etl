<?php

namespace markhuot\etl\exceptions;

use markhuot\etl\base\Frame;

class TransformerException extends \Exception
{
    public function __construct(public Frame $frame, \Throwable $previous)
    {
        parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
    }
}
