<?php

namespace markhuot\etl\transformers;

use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;

class CopyTransformer extends Transformer
{
    /**
     * @param Frame<mixed> $source
     * @param Frame<mixed> $destination
     * @return void
     */
    public function transform(Frame $source, Frame $destination): void
    {
        $destination->data = $source->data;
    }
}
