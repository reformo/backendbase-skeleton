<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Seeders\EntrySeeder;
use PHPUnit\Framework\Attributes\Test;

final class EntrySeederTest extends DoctrineEntryRepositoryTestCase
{
    #[Test]
    public function itSeedsTheEntryTableIdempotently(): void
    {
        $seeder = new EntrySeeder();

        $seeder->seed($this->connection);
        $seeder->seed($this->connection);

        $row = $this->connection->fetchAssociative(
            "SELECT type, lookup_group, lookup_key, updated_at FROM example_table WHERE lookup_key = 'example-seeded-item'",
        );
        self::assertIsArray($row);
        self::assertSame('system', $row['type']);
        self::assertSame('examples', $row['lookup_group']);
        self::assertNotEmpty($row['updated_at']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_table'));
    }
}
