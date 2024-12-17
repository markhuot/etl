<?php

use markhuot\etl\auditors\Sqlite;
use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;
use markhuot\etl\connections\ArrayConnection;
use markhuot\etl\Etl;
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
    expect($result->etl->auditor->getStatusForKey(0))
        ->sourceKey->not->toBeNull()
        ->lastImport->toBeNull();
    expect($result->etl->auditor->getTotals())
        ->{'0'}->toBe(0)
        ->{'1'}->toBe(3);
});

it('audits sources', function () {
    $result = \etl()
        ->from(new ArrayConnection([1]))
        ->to(new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->start();

    expect($result->etl->auditor)
        ->getStatusForKey(0)->lastImport->not->toBeNull()
        ->getTotals()->{'0'}->toBe(1);
});

it('uses collections', function () {
    $result = \etl()
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

    expect($result->etl->auditor->getImportStats())
        ->foo->toBe([1,1])
        ->bar->toBe([0,1])
        ->baz->toBe([1,1]);

    expect($result->etl->auditor)
        ->trackFrame(new Frame(1, sourceKey: 1, collection: 'foo'))->lastImport->not->toBeNull()
        ->trackFrame(new Frame(2, sourceKey: 2, collection: 'bar'))->lastImport->toBeNull()
        ->trackFrame(new Frame(3, sourceKey: 3, collection: 'baz'))->lastImport->not->toBeNull();
});

it('retains existing audit', function () {
    $process = \etl()
        ->from(new ArrayConnection([1, 2, 3]))
        ->to($destination = new class extends ArrayConnection {
            public function upsertFrame(Frame $frame): void {
                $uuid = $frame->destinationKey ?? base64_encode(random_bytes(32));
                $this->array[$uuid] = $frame->data;
                $frame->destinationKey ??= $uuid;

                $this->trigger('upsert', [$frame]);
            }
        })
        ->addTransformer(new CopyTransformer);

    $result = $process->start();
    $originalStatus = $result->etl->auditor->getStatusForKey(0);
    expect($originalStatus)->destinationKey->not->toBeNull();

    $result = $process->start();
    $repeatedStatus = $result->etl->auditor->getStatusForKey(0);
    expect($repeatedStatus)->destinationKey->toBe($originalStatus->destinationKey);
});

it('handles buffered destinations', function () {
    $process = \etl()
        ->from(new ArrayConnection([1, 2, 3]))
        ->to($destination = new class extends \markhuot\etl\connections\BufferingConnection {
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

    $result = $process->start();
    $originalStatus = $result->etl->auditor->getStatusForKey(0);
    expect($originalStatus)->destinationKey->not->toBeNull();

    $result = $process->start();
    $repeatedStatus = $result->etl->auditor->getStatusForKey(0);
    expect($repeatedStatus)->destinationKey->toBe($originalStatus->destinationKey);
});
