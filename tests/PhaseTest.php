<?php

use markhuot\etl\base\Frame;
use markhuot\etl\base\TransformerInterface;
use markhuot\etl\connections\ArrayConnection;
use markhuot\etl\phases\DefaultPhase;
use markhuot\etl\phases\RelationsPhase;
use markhuot\etl\transformers\CopyTransformer;

it('runs the default phase when unspecified', function () {
    etl()
        ->from(new ArrayConnection([1,2,3]))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->addTransformer(new class implements TransformerInterface, RelationsPhase {
            public function canTransform(Frame $source): bool {
                return true;
            }
            public function transform(Frame $source, Frame $destination): void {
                $destination->data = $source->data * 2;
            }
        })
        ->start();

    expect($destination->array)->toEqualCanonicalizing([1,2,3]);
});

it('runs a secondary phase when specified', function () {
    etl()
        ->from(new ArrayConnection([1,2,3]))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->addTransformer(new class implements TransformerInterface, RelationsPhase {
            public function canTransform(Frame $source): bool {
                return true;
            }
            public function transform(Frame $source, Frame $destination): void {
                $destination->data = $source->data * 2;
            }
        })
        ->start(phase: RelationsPhase::class);

    expect($destination->array)->toEqualCanonicalizing([2,4,6]);
});

it('keeps audits of all phases', function () {
    ($voyage = etl())
        ->from(new ArrayConnection([1]))
        ->to(new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->addTransformer(new class implements TransformerInterface, RelationsPhase {
            public function canTransform(Frame $source): bool {
                return true;
            }
            public function transform(Frame $source, Frame $destination): void {
                $destination->data = $source->data * 2;
            }
        });

    $voyage->start(phase: DefaultPhase::class);
    $voyage->start(phase: RelationsPhase::class);

    expect($voyage->getAuditor()->getStatusForKey(DefaultPhase::class, 'default', 0))->not->toBeNull();
    expect($voyage->getAuditor()->getStatusForKey(RelationsPhase::class, 'default', 0))->not->toBeNull();
});
