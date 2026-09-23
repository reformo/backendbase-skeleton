<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root;

use Backendbase\Shared\Exception\InvalidUserInput;

use function array_keys;
use function filter_var;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;

use const FILTER_VALIDATE_EMAIL;

final class AuthenticationRequestInput
{
    private const array FIELDS = ['email', 'password'];

    private const int MAX_EMAIL_LENGTH = 254;

    private const int MAX_PASSWORD_LENGTH = 1024;

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

        foreach (array_keys($value) as $field) {
            if (! is_string($field) || ! in_array($field, self::FIELDS, true)) {
                throw InvalidUserInput::create('The authentication payload contains an unsupported field.');
            }
        }

        return $value;
    }

    public static function email(mixed $value): string
    {
        if (
            ! is_string($value)
            || mb_strlen($value) > self::MAX_EMAIL_LENGTH
            || filter_var($value, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw InvalidUserInput::create('The email value must be a valid email address.');
        }

        return $value;
    }

    public static function password(mixed $value): string
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > self::MAX_PASSWORD_LENGTH) {
            throw InvalidUserInput::create('The password value must be a non-empty string.');
        }

        return $value;
    }
}
