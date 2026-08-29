<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthentication;
use UnexpectedValueException;

use function array_unique;
use function array_values;
use function is_string;

final class AccountAuthenticationMapper
{
    /** @param list<array<string, mixed>> $rows */
    public static function account(array $rows): AccountAuthentication|null
    {
        if ($rows === []) {
            return null;
        }

        $account    = $rows[0];
        $privileges = [];
        foreach ($rows as $row) {
            $privilege = $row['privilege_slug'] ?? null;
            if ($privilege === null) {
                continue;
            }

            $privileges[] = self::string($privilege, 'privilege_slug');
        }

        return new AccountAuthentication(
            self::string($account['uuid'] ?? null, 'uuid'),
            self::string($account['email'] ?? null, 'email'),
            self::string($account['password_hash'] ?? null, 'password_hash'),
            array_values(array_unique($privileges)),
        );
    }

    private static function string(mixed $value, string $field): string
    {
        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException('The persisted ' . $field . ' value must be a non-empty string.');
        }

        return $value;
    }
}
