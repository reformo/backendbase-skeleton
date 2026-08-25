<?php

declare(strict_types=1);

namespace Backendbase\Seeders;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

/**
 * EXAMPLE seeder — the reference implementation of the Backendbase seeder pattern.
 * It is NOT invoked by any migration; copy it when a feature needs seed data.
 *
 * Pattern:
 * - Seeders live in this folder and are autoloaded as `Backendbase\Seeders` (see composer.json).
 * - A seeder is invoked from a Doctrine migration's up() as its final statement:
 *       (new ExampleSeeder())->seed($this->connection);
 *   Migrations run automatically on deploy, so approved seed data ships with the release.
 * - Every seeder MUST be idempotent — guard each insert with an existence check so
 *   re-running the migration never duplicates rows.
 * - Seed ONLY reference/lookup data the user explicitly approved. Environment-specific
 *   or test-only data does not belong in a seeder.
 */
final class ExampleSeeder
{
    public function seed(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM example_table
              WHERE type = :type AND type_target_id IS NULL
                AND lookup_group = :lookupGroup AND lookup_key = :lookupKey AND deleted_at IS NULL',
            ['type' => 'system', 'lookupGroup' => 'examples', 'lookupKey' => 'example-seeded-item'],
        );

        if ($exists !== false) {
            return;
        }

        $now = DateTimeImmutable::create()->format('Y-m-d H:i:s');
        $connection->insert('example_table', [
            'uuid'           => Uuid::uuid4()->toString(),
            'type'           => 'system',
            'type_target_id' => null,
            'lookup_group'   => 'examples',
            'lookup_key'     => 'example-seeded-item',
            'lookup_value'   => 'Example seeded item',
            'details'        => '{}',
            'is_active'      => 1,
            'updated_at'     => $now,
            'created_at'     => $now,
        ]);
    }
}
