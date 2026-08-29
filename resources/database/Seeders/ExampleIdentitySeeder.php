<?php

declare(strict_types=1);

namespace Backendbase\Seeders;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

final class ExampleIdentitySeeder
{
    private const string ACCOUNT_EMAIL = 'mehmet@mkorkmaz.com';

    private const string ACCOUNT_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$6+/ybOKBjUSFxFdWT9LVkQ$LdaPc8/LZaHbYGS1YRRKG1/9h6fRvNCJGJeZFrZfLBA';

    private const array PRIVILEGES = [
        'example.add' => 'Add examples',
        'example.change' => 'Change examples',
        'example.remove' => 'Remove examples',
    ];

    public function seed(Connection $connection): void
    {
        $privilegeIds = $this->seedPrivileges($connection);
        $accountId    = $this->seedAccount($connection);
        $this->seedAccountPrivileges($connection, $accountId, $privilegeIds);
    }

    /** @return array<string, int> */
    private function seedPrivileges(Connection $connection): array
    {
        $privilegeIds = [];
        foreach (self::PRIVILEGES as $slug => $title) {
            $privilegeIds[$slug] = $this->seedPrivilege($connection, $slug, $title);
        }

        return $privilegeIds;
    }

    private function seedPrivilege(Connection $connection, string $slug, string $title): int
    {
        $id = $connection->fetchOne(
            'SELECT id FROM example_privileges WHERE slug = :slug',
            ['slug' => $slug],
        );
        if ($id !== false) {
            return (int) $id;
        }

        $connection->insert('example_privileges', [
            'uuid' => Uuid::uuid7()->toString(),
            'title' => $title,
            'slug' => $slug,
            'created_at' => $this->now(),
            'deleted_at' => null,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function seedAccount(Connection $connection): int
    {
        $id = $connection->fetchOne(
            'SELECT id FROM example_accounts WHERE email = :email',
            ['email' => self::ACCOUNT_EMAIL],
        );
        if ($id !== false) {
            return (int) $id;
        }

        $connection->insert('example_accounts', [
            'uuid' => Uuid::uuid7()->toString(),
            'email' => self::ACCOUNT_EMAIL,
            'password_hash' => self::ACCOUNT_PASSWORD_HASH,
            'created_at' => $this->now(),
            'deleted_at' => null,
        ]);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int> $privilegeIds */
    private function seedAccountPrivileges(Connection $connection, int $accountId, array $privilegeIds): void
    {
        foreach ($privilegeIds as $privilegeId) {
            $this->seedAccountPrivilege($connection, $accountId, $privilegeId);
        }
    }

    private function seedAccountPrivilege(Connection $connection, int $accountId, int $privilegeId): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM example_account_privileged '
            . 'WHERE account_id = :accountId AND privilege_id = :privilegeId',
            ['accountId' => $accountId, 'privilegeId' => $privilegeId],
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('example_account_privileged', [
            'uuid' => Uuid::uuid7()->toString(),
            'account_id' => $accountId,
            'privilege_id' => $privilegeId,
            'created_at' => $this->now(),
            'expired_at' => null,
        ]);
    }

    private function now(): string
    {
        return DateTimeImmutable::create()->format('Y-m-d H:i:s.u');
    }
}
