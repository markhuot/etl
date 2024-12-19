<?php

use markhuot\etl\auditors\Sqlite;
use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;
use markhuot\etl\connections\ArrayConnection;
use markhuot\etl\Etl;
use markhuot\etl\phases\DefaultPhase;
use markhuot\etl\transformers\CopyTransformer;

it('skips transforms on audits', function () {
    $result = \etl()
        ->from(new ArrayConnection(['a', 'b', 'c']))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                throw new \RuntimeException('Should not run in audit mode');
            }
        })
        ->start(auditOnly: true);

    expect($destination)->array->toHaveCount(0);
    expect($result->etl->getAuditor()->getStatusForKey(DefaultPhase::class, 'default', 0))
        ->sourceKey->not->toBeNull()
        ->lastImport->toBeNull();
    expect($result->etl->getAuditor()->getImportStats()[DefaultPhase::class]['default'])->toBe([0, 3]);
});

it('audits sources', function () {
    $result = \etl()
        ->from(new ArrayConnection([1]))
        ->to(new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->start();

    expect($result->etl->getAuditor())
        ->getStatusForKey(DefaultPhase::class, 'default', 0)->lastImport->not->toBeNull()
        ->getImportStats()->{DefaultPhase::class}->default->toBe([1,1]);
});

it('uses collections', function () {
    ($voyage = \etl())
        ->from(new class extends ArrayConnection {
            public function walk(): Generator {
                yield [new Frame(data: 1, sourceKey: 1, collection: 'foo')];
                yield [new Frame(data: 2, sourceKey: 2, collection: 'bar')];
                yield [new Frame(data: 3, sourceKey: 3, collection: 'baz')];
            }
        })
        ->to(new ArrayConnection)
        ->addTransformer(new class extends CopyTransformer {
            public function transform(Frame $source, Frame $destination): void {
                if ($source->sourceKey === '2') {
                    throw new RuntimeException('Transform error');
                }

                parent::transform($source, $destination);
            }
        })
        ->start();

    expect($voyage->getAuditor()->getImportStats()[DefaultPhase::class])
        ->foo->toBe([1,1])
        ->bar->toBe([0,1])
        ->baz->toBe([1,1]);

    expect($voyage->getAuditor())
        ->getStatusForKey(DefaultPhase::class, 'foo', 1)->lastImport->not->toBeNull()
        ->getStatusForKey(DefaultPhase::class, 'bar', 2)->lastError->not->toBeNull()
        ->getStatusForKey(DefaultPhase::class, 'baz', 3)->lastImport->not->toBeNull();
});

it('uses phases', function () {
    ($voyage = \etl())
        ->from(new class extends ArrayConnection {
            public function walk(): Generator {
                yield [new Frame(data: 1, sourceKey: 0, collection: 'foo')];
            }
        })
        ->to(new ArrayConnection)
        ->addTransformer(new CopyTransformer);

    $voyage->start(phase: DefaultPhase::class);
    $voyage->start(phase: \markhuot\etl\phases\RelationsPhase::class);


    expect($voyage->getAuditor())
        ->getStatusForKey(DefaultPhase::class, 'foo', 0)->lastImport->not->toBeNull()
        ->getStatusForKey(\markhuot\etl\phases\RelationsPhase::class, 'foo', 0)->lastImport->not->toBeNull();
});

it('retains existing audit', function () {
    $voyage = ($etl = \etl())
        ->from(new ArrayConnection([1, 2, 3]))
        ->to(new class extends ArrayConnection {
            public function upsertFrame(Frame $frame): void {
                $uuid = $frame->destinationKey ?? base64_encode(random_bytes(32));
                $this->array[$uuid] = $frame->data;
                $frame->destinationKey ??= $uuid;

                $this->trigger('upsert', [$frame]);
            }
        })
        ->addTransformer(new CopyTransformer);

    $voyage->start();
    $originalStatus = $etl->getAuditor()->getStatusForKey(DefaultPhase::class, 'default', 0);
    expect($originalStatus)->destinationKey->not->toBeNull();

    $voyage->start();
    $repeatedStatus = $etl->getAuditor()->getStatusForKey(DefaultPhase::class, 'default', 0);
    expect($repeatedStatus)->destinationKey->toBe($originalStatus->destinationKey);
});

it('handles buffered destinations', function () {
    ($voyage = \etl())
        ->from(new ArrayConnection([1, 2, 3]))
        ->to(new class extends \markhuot\etl\connections\BufferingConnection {
            protected $array = [];
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
        ->addTransformer(new CopyTransformer);

    $voyage->start();
    $originalStatus = $voyage->getAuditor()->getStatusForKey(DefaultPhase::class, 'default', 0);
    expect($originalStatus)->destinationKey->not->toBeNull();

    $voyage->start();
    $repeatedStatus = $voyage->getAuditor()->getStatusForKey(DefaultPhase::class, 'default', 0);
    expect($repeatedStatus)->destinationKey->toBe($originalStatus->destinationKey);
});
