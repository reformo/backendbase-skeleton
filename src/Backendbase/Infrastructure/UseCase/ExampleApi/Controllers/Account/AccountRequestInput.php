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
use function array_unique;
use function count;
use function is_array;
use function is_string;
use function mb_strlen;

use const SORT_REGULAR;

final class AccountRequestInput
{
    private const int MAX_EMAIL_LENGTH = 254;

    private const int MAX_PASSWORD_LENGTH = 1024;

    private const int MIN_PASSWORD_LENGTH = 12;

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
        return AccountPayload::registration($value);
    }

    /**
     * @param array<array-key, mixed>|object|null $value
     *
     * @return array<string, mixed>
     */
    public static function revisionPayload(array|object|null $value): array
    {
        return AccountPayload::revision($value);
    }

    public static function email(mixed $value): Email
    {
        if (! is_string($value) || mb_strlen($value) > self::MAX_EMAIL_LENGTH) {
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
        if (
            ! is_string($value)
            || mb_strlen($value) < self::MIN_PASSWORD_LENGTH
            || mb_strlen($value) > self::MAX_PASSWORD_LENGTH
        ) {
            throw InvalidUserInput::create('The password length must be between 12 and 1024 characters.');
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

        if (count($value) > AccountPrivileges::MAX_COUNT || count(array_unique($value, SORT_REGULAR)) !== count($value)) {
            throw InvalidUserInput::create('The privilegeSlugs value must contain at most 100 unique values.');
        }

        $slugs = [];
        foreach ($value as $slug) {
            if (! is_string($slug) || $slug === '' || mb_strlen($slug) > AccountPrivileges::MAX_SLUG_LENGTH) {
                throw InvalidUserInput::create('Each privilege slug must contain between 1 and 100 characters.');
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
}
