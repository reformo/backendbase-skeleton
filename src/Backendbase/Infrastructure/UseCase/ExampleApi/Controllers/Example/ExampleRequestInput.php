<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example;

use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Exception\InvalidUserInput;

use function filter_var;
use function is_int;
use function is_string;

use const FILTER_VALIDATE_INT;

final class ExampleRequestInput
{
    public static function type(mixed $value): ExampleType
    {
        $type = is_string($value) ? ExampleType::tryFrom($value) : null;
        if ($type === null) {
            throw InvalidUserInput::create('The example type is invalid.');
        }

        return $type;
    }

    public static function optionalTypeTargetId(mixed $value): int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::positiveInteger($value, 'typeTargetId');
    }

    public static function positiveInteger(mixed $value, string $name): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw InvalidUserInput::create('The ' . $name . ' value must be a positive integer.');
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($integer === false) {
            throw InvalidUserInput::create('The ' . $name . ' value must be a positive integer.');
        }

        return $integer;
    }
}
