<?php

namespace markhuot\etl\helpers;


use RuntimeException;
use Throwable;

/**
 * Throw the given exception if the given condition is true.
 *
 * @phpstan-assert !true $condition
 */
function throw_if(mixed $condition, Throwable|string $exception = 'RuntimeException', mixed ...$parameters): mixed
{
    if ($condition) {
        if (is_string($exception) && class_exists($exception)) {
            $exception = new $exception(...$parameters);
        }

        // @phpstan-ignore-next-line not sure why PHPStan can't reason about this...
        throw is_string($exception) ? new RuntimeException($exception) : $exception;
    }

    return $condition;
}

/**
 * Throw the given exception unless the given condition is true.
 *
 * @phpstan-assert true $condition
 */
function throw_unless(mixed $condition, Throwable|string $exception = 'RuntimeException', mixed ...$parameters): mixed
{
    throw_if(! $condition, $exception, ...$parameters);

    return $condition;
}
