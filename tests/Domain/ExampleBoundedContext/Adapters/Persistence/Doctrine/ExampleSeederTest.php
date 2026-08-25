<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine;

use Backendbase\Seeders\ExampleSeeder;
use PHPUnit\Framework\Attributes\Test;

final class ExampleSeederTest extends DoctrineExampleRepositoryTestCase
{
    #[Test]
    public function itSeedsTheExampleTableIdempotently(): void
    {
        $seeder = new ExampleSeeder();

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
