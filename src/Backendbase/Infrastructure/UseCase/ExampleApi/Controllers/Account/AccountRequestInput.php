<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account;

use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use SensitiveParameterValue;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function in_array;
use function is_array;
use function is_string;

final class AccountRequestInput
{
    private const array REGISTRATION_FIELDS = ['email', 'password', 'privilegeSlugs'];

    private const array REVISION_FIELDS = ['email', 'password', 'privilegeSlugs'];

    public static function accessControl(mixed $value): AccessControl
    {
        if (! $value instanceof AccessControl) {
            throw AuthorizationExpired::create('The authorization context is missing.');
        }

        return $value;
    }

    public static function accountId(mixed $value): AccountId
    {
        if (! is_string($value) || $value === '') {
            throw InvalidUserInput::create('The account-uuid path parameter must be a UUID string.');
        }

        return AccountId::fromString($value);
    }

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    public static function registrationPayload(array|object|null $value): array
    {
        $payload = self::payload($value);
        self::rejectUnexpectedFields($payload, self::REGISTRATION_FIELDS);
        self::requireFields($payload, self::REGISTRATION_FIELDS);

        return $payload;
    }

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    public static function revisionPayload(array|object|null $value): array
    {
        $payload = self::payload($value);
        self::rejectUnexpectedFields($payload, self::REVISION_FIELDS);
        if ($payload === []) {
            throw InvalidUserInput::create('The account revision payload must contain a field.');
        }

        return $payload;
    }

    public static function email(mixed $value): Email
    {
        if (! is_string($value)) {
            throw InvalidUserInput::create('The email value must be a valid email address.');
        }

        return new Email($value);
    }

    /** @param array<string, mixed> $payload */
    public static function optionalEmail(array $payload): Email|null
    {
        if (! array_key_exists('email', $payload)) {
            return null;
        }

        return self::email($payload['email']);
    }

    public static function passwordHash(mixed $value): PasswordHash
    {
        if (! is_string($value) || $value === '') {
            throw InvalidUserInput::create('The password value must be a non-empty string.');
        }

        return PasswordHash::fromPassword(new SensitiveParameterValue($value));
    }

    /** @param array<string, mixed> $payload */
    public static function optionalPasswordHash(array $payload): PasswordHash|null
    {
        if (! array_key_exists('password', $payload)) {
            return null;
        }

        return self::passwordHash($payload['password']);
    }

    public static function privilegeSlugs(mixed $value): AccountPrivileges
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidUserInput::create('The privilegeSlugs value must be an array of privilege slugs.');
        }

        $slugs = [];
        foreach ($value as $slug) {
            if (! is_string($slug) || $slug === '') {
                throw InvalidUserInput::create('Each privilege slug must be a non-empty string.');
            }

            $slugs[] = $slug;
        }

        return new AccountPrivileges($slugs);
    }

    /** @param array<string, mixed> $payload */
    public static function optionalPrivilegeSlugs(array $payload): AccountPrivileges|null
    {
        if (! array_key_exists('privilegeSlugs', $payload)) {
            return null;
        }

        return self::privilegeSlugs($payload['privilegeSlugs']);
    }

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    private static function payload(array|object|null $value): array
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

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $allowedFields
     */
    private static function rejectUnexpectedFields(array $payload, array $allowedFields): void
    {
        foreach (array_keys($payload) as $field) {
            if (! in_array($field, $allowedFields, true)) {
                throw InvalidUserInput::create('The account payload contains an unsupported field.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $requiredFields
     */
    private static function requireFields(array $payload, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $payload)) {
                throw InvalidUserInput::create('The ' . $field . ' value is required.');
            }
        }
    }
}
