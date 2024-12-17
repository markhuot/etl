<?php

use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;
use markhuot\etl\connections\ArrayConnection;

it('skips errors in production', function () {
    \etl()
        ->from(new ArrayConnection([1]))
        ->to(new ArrayConnection)
        ->devMode(false)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                throw new RuntimeException('A transformer error');
            }
        })
        ->start();

    expect(true)->toBeTrue();
});

it('continues on exceptions in production', function () {
    $result = \etl()
        ->devMode(false)
        ->from(new ArrayConnection([1,2,3]))
        ->to(new ArrayConnection)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                if ($source->data === 2) {
                    throw new RuntimeException('Error on row two');
                }
            }
        })
        ->start();

    expect($result)->errors->toHaveCount(1);
    expect($result)->errors->{'0'}->getMessage()->toBe('Error on row two');
});
