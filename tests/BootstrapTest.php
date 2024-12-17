<?php

use markhuot\etl\base\Frame;
use markhuot\etl\base\Transformer;
use markhuot\etl\connections\ArrayConnection;
use markhuot\etl\Etl;
use markhuot\etl\transformers\CopyTransformer;

it('bootstraps', function () {
    \etl()
        ->from(new ArrayConnection([
            ['firstName' => 'gob', 'lastName' => 'bluth'],
            ['firstName' => 'michael', 'lastName' => 'bluth'],
            ['firstName' => 'lindsey', 'lastName' => 'bluth'],
        ]))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->start();

    expect($destination->array[1]['firstName'])->toBe('michael');
});

it('transforms', function () {
    \etl()
        ->from(new ArrayConnection([
            ['firstName' => 'gob', 'lastName' => 'bluth'],
            ['firstName' => 'michael', 'lastName' => 'bluth'],
            ['firstName' => 'lindsey', 'lastName' => 'bluth'],
        ]))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                $destination->data['fullName'] = implode(' ', [
                    ucfirst($source->data['firstName']),
                    ucfirst($source->data['lastName']),
                ]);
            }
        })
        ->start();

    expect($destination->array[1]['fullName'])->toBe('Michael Bluth');
});

it('handles multiple transformers', function () {
    \etl()
        ->from(new ArrayConnection([
            ['firstName' => 'gob', 'lastName' => 'bluth'],
            ['firstName' => 'michael', 'lastName' => 'bluth'],
            ['firstName' => 'lindsey', 'lastName' => 'bluth'],
        ]))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                $destination->data['fullName'] = implode(' ', [
                    ucfirst($source->data['firstName']),
                    ucfirst($source->data['lastName']),
                ]);
            }
        })
        ->addTransformer(new class extends Transformer {
            public function transform(Frame $source, Frame $destination): void {
                $destination->data['email'] = sprintf('%s@%s.com',
                    $source->data['firstName'],
                    $source->data['lastName'],
                );
            }
        })
        ->start();

    expect($destination->array[1])
        ->fullName->toBe('Michael Bluth')
        ->email->toBe('michael@bluth.com');
});

it('spreads one source row to many frames', function () {
    \etl()
        ->from(new class ([
            ['lineItems' => [1, 2, 3]],
            ['lineItems' => [4, 5, 6]],
        ]) extends ArrayConnection {
            public function walk(): Generator {
                foreach ($this->array as $order) {
                    yield array_map(fn ($lineItem) => new Frame(data: $lineItem, sourceKey: $lineItem), $order['lineItems']);
                }
            }
        })
        ->to($destination = new ArrayConnection)
        ->addTransformer(new CopyTransformer)
        ->start();

    expect($destination->array)->toEqualCanonicalizing([1,2,3,4,5,6]);
});

it('supports conditional transformers', function () {
    \etl()
        ->from(new ArrayConnection([
            1, 2, 3,
        ]))
        ->to($destination = new ArrayConnection)
        ->addTransformer(new class extends Transformer {
            public function canTransform(Frame $frame): bool {
                return false;
            }
            public function transform(Frame $source, Frame $destination): void {
                $destination->data = $source->data * 4;
            }
        })
        ->addTransformer(new class extends Transformer {
            public function canTransform(Frame $frame): bool {
                return true;
            }
            public function transform(Frame $source, Frame $destination): void {
                $destination->data = $source->data * 2;
            }
        })
        ->start();

    expect($destination->array)->toEqualCanonicalizing([2, 4, 6]);
});
