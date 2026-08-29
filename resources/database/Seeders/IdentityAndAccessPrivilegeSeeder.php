<?php

declare(strict_types=1);

namespace Backendbase\Seeders;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

final class IdentityAndAccessPrivilegeSeeder
{
    private const array PRIVILEGES = [
        'account.list' => 'List accounts',
        'account.register' => 'Register accounts',
        'account.retire' => 'Retire accounts',
        'account.revise' => 'Revise accounts',
        'example.add' => 'Add examples',
        'example.change' => 'Change examples',
        'example.remove' => 'Remove examples',
    ];

    public function seed(Connection $connection): void
    {
        foreach (self::PRIVILEGES as $slug => $title) {
            $this->seedPrivilege($connection, $slug, $title);
        }
    }

    private function seedPrivilege(Connection $connection, string $slug, string $title): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM example_privileges WHERE slug = :slug',
            ['slug' => $slug],
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('example_privileges', [
            'uuid' => Uuid::uuid7()->toString(),
            'title' => $title,
            'slug' => $slug,
            'created_at' => DateTimeImmutable::create()->format('Y-m-d H:i:s.u'),
            'deleted_at' => null,
        ]);
    }
}
