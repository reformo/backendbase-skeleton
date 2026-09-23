<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Account;

use Backendbase\Shared\Exception\InvalidUserInput;

use function array_key_exists;
use function array_keys;
use function in_array;
use function is_array;
use function is_string;

final class AccountPayload
{
    private const array FIELDS = ['email', 'password', 'privilegeSlugs'];

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    public static function registration(array|object|null $value): array
    {
        $payload = self::object($value);
        self::rejectUnexpectedFields($payload);
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $payload)) {
                throw InvalidUserInput::create('The ' . $field . ' value is required.');
            }
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    public static function revision(array|object|null $value): array
    {
        $payload = self::object($value);
        self::rejectUnexpectedFields($payload);
        if ($payload === []) {
            throw InvalidUserInput::create('The account revision payload must contain a field.');
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    private static function object(array|object|null $value): array
    {
        if (! is_array($value)) {
            throw InvalidUserInput::create('The account payload must be an object.');
        }

        $payload = [];
        foreach ($value as $field => $fieldValue) {
            if (! is_string($field)) {
                throw InvalidUserInput::create('The account payload contains an unsupported field.');
            }

            $payload[$field] = $fieldValue;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private static function rejectUnexpectedFields(array $payload): void
    {
        foreach (array_keys($payload) as $field) {
            if (! in_array($field, self::FIELDS, true)) {
                throw InvalidUserInput::create('The account payload contains an unsupported field.');
            }
        }
    }
}
