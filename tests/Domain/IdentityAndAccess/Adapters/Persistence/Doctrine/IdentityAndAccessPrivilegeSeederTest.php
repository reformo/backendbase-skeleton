<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Seeders\IdentityAndAccessPrivilegeSeeder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityAndAccessPrivilegeSeederTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE example_privileges ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, '
            . 'created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
    }

    #[Test]
    public function itSeedsIdentityAndAccessPrivilegesIdempotently(): void
    {
        $seeder = new IdentityAndAccessPrivilegeSeeder();

        $seeder->seed($this->connection);
        $seeder->seed($this->connection);

        self::assertSame(7, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_privileges'));
        self::assertSame(
            [
                'account.list',
                'account.register',
                'account.retire',
                'account.revise',
                'example.add',
                'example.change',
                'example.remove',
            ],
            $this->connection->fetchFirstColumn('SELECT slug FROM example_privileges ORDER BY slug ASC'),
        );
    }
}
