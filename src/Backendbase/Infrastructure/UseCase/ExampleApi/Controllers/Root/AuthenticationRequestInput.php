<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root;

use Backendbase\Shared\Exception\InvalidUserInput;

use function filter_var;
use function is_array;
use function is_string;

use const FILTER_VALIDATE_EMAIL;

final class AuthenticationRequestInput
{
    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<array-key, mixed>
     */
    public static function payload(array|object|null $value): array
    {
        if (! is_array($value)) {
            throw InvalidUserInput::create('The authentication payload must be an object.');
        }

        return $value;
    }

    public static function email(mixed $value): string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidUserInput::create('The email value must be a valid email address.');
        }

        return $value;
    }

    public static function password(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw InvalidUserInput::create('The password value must be a non-empty string.');
        }

        return $value;
    }
}
