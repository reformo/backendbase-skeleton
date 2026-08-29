<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use UnexpectedValueException;

use function array_unique;
use function array_values;
use function ctype_digit;
use function is_int;
use function is_string;

final class AccountPersistenceMapper
{
    /** @param list<array<string, mixed>> $rows */
    public static function account(array $rows): Account
    {
        if ($rows === []) {
            throw new UnexpectedValueException('The persisted account is missing.');
        }

        $account    = $rows[0];
        $privileges = [];
        foreach ($rows as $row) {
            $privilegeSlug = $row['privilege_slug'] ?? null;
            if ($privilegeSlug === null) {
                continue;
            }

            $privileges[] = self::string($privilegeSlug, 'privilege_slug');
        }

        return Account::reconstitute(
            AccountId::fromString(self::string($account['uuid'] ?? null, 'uuid')),
            new Email(self::string($account['email'] ?? null, 'email')),
            PasswordHash::create(self::string($account['password_hash'] ?? null, 'password_hash')),
            new AccountPrivileges(array_values(array_unique($privileges))),
        );
    }

    public static function positiveInteger(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && $value !== '0') {
            return (int) $value;
        }

        throw new UnexpectedValueException('The persisted ' . $field . ' value must be a positive integer.');
    }

    private static function string(mixed $value, string $field): string
    {
        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException('The persisted ' . $field . ' value must be a non-empty string.');
        }

        return $value;
    }
}
