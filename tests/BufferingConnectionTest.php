<?php

use markhuot\etl\base\Frame;
use markhuot\etl\connections\ArrayConnection;
use markhuot\etl\connections\BufferingConnection;
use markhuot\etl\transformers\CopyTransformer;

it('sends a final page of frames', function () {
    \etl()
        ->from(new ArrayConnection([1,2,3,4,5,6,7,8,9,10]))
        ->to($destination = new class extends BufferingConnection {
            protected int $batchSize = 3;
            public $array = [];
            public function prepareFrame(Frame $frame): Frame {
                return (clone $frame)->setData([]);
            }
            public function sendBuffer(): void {
                foreach ($this->frameBuffer as $frame) {
                    $uuid = $frame->destinationKey ?? base64_encode(random_bytes(32));
                    $this->array[$uuid] = $frame->data;
                    $frame->destinationKey ??= $uuid;
                }
            }
        })
        ->addTransformer(new CopyTransformer)
        ->start();

    expect($destination->array)->toHaveCount(10);
});
