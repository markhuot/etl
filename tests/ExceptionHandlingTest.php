<?php

use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;
use markhuot\etl\connections\ArrayConnection;

it('throws exceptions in devMode', function () {
    $this->expectExceptionObject(new RuntimeException('A transformer error'));

    \etl()
        ->from(new ArrayConnection([1]))
        ->to(new ArrayConnection)
        ->devMode(true)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                throw new RuntimeException('A transformer error');
            }
        })
        ->start();
});

it('skips errors in production', function () {
    ($voyage = \etl())
        ->from(new ArrayConnection([1]))
        ->to(new ArrayConnection)
        ->devMode(false)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                throw new RuntimeException('A transformer error');
            }
        })
        ->start();

    expect($voyage->getAuditor()->getImportStats())->default->toBe([0,1]);
});
