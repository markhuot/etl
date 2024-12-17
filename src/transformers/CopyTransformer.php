<?php

namespace markhuot\etl\transformers;

use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;

class CopyTransformer extends Transformer
{
    public function transform(Frame $source, Frame $destination): void
    {
        $destination->data = $source->data;
    }
}
