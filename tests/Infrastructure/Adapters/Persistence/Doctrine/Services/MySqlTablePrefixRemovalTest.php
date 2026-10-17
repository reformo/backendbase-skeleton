<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Backendbase\Migrations\Version20261014020000;
use Backendbase\Migrations\Version20261017000000;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Services\IsolatedMySql;

use function getenv;
use function str_repeat;
use function str_replace;

final class MySqlTablePrefixRemovalTest extends TestCase
{
    public function testForwardAndReversePreserveRowsAndIndexDefinitions(): void
    {
        $fixture = self::fixture();
        try {
            $connection = $fixture->connection();
            self::apply($connection, new Version20261014020000($connection, new NullLogger()), 'up');
            $identifier = str_repeat('a', 16);
            $connection->insert('identity_account', ['id' => $identifier, 'normalized_email' => 'rename@example.com', 'normalized_mobile' => '+905551234567', 'created_at' => '2026-10-17 00:00:00', 'state' => '{"enabled":true}', 'password_hash' => 'preserved-hash']);
            $connection->insert('identity_user', ['id' => $identifier, 'account_id' => $identifier, 'created_at' => '2026-10-17 00:00:00', 'state' => '{"approved":true}']);
            $connection->insert('identity_admin', ['id' => $identifier, 'account_id' => $identifier, 'created_at' => '2026-10-17 00:00:00', 'state' => '{"approved":false}']);
            $connection->insert('identity_operator_action', ['id' => $identifier, 'target_id' => $identifier, 'action_name' => 'approve', 'bootstrap_key' => 'first-admin', 'operator_name' => 'operator', 'recorded_at' => '2026-10-17 00:00:00']);
            $tables        = ['account', 'user', 'admin', 'operator_action'];
            $before        = self::rows($connection, $tables, 'identity_');
            $schemaManager = $connection->createSchemaManager();
            $beforeSchema  = $schemaManager->introspectSchema();
            self::apply($connection, new Version20261017000000($connection, new NullLogger()), 'up');
            self::assertSame($before, self::rows($connection, $tables, ''));
            self::assertSame(['account', 'admin', 'contact_info', 'operator_action', 'user'], $schemaManager->listTableNames());
            foreach ($tables as $table) {
                $this->assertIndexesPreserved($beforeSchema, $schemaManager->introspectSchema(), $table);
            }

            self::apply($connection, new Version20261017000000($connection, new NullLogger()), 'down');
            self::assertSame($before, self::rows($connection, $tables, 'identity_'));
        } finally {
            $fixture->destroy();
        }
    }

    public function testExistingTargetStopsBeforeRenamingTheFirstTable(): void
    {
        $fixture = self::fixture();
        try {
            $connection = $fixture->connection();
            self::apply($connection, new Version20261014020000($connection, new NullLogger()), 'up');
            $connection->executeStatement('CREATE TABLE account (id INT PRIMARY KEY)');
            $this->expectException(TableExistsException::class);
            self::apply($connection, new Version20261017000000($connection, new NullLogger()), 'up');
        } finally {
            $connection    = $fixture->connection();
            $schemaManager = $connection->createSchemaManager();
            self::assertContains('identity_account', $schemaManager->listTableNames());
            $fixture->destroy();
        }
    }

    private function assertIndexesPreserved(Schema $before, Schema $after, string $name): void
    {
        $oldTable = $before->getTable('identity_' . $name);
        $newTable = $after->getTable($name);
        foreach ($oldTable->getIndexes() as $index) {
            $newIndex = $newTable->getIndex(str_replace('identity_', '', $index->getName()));
            self::assertSame($index->getColumns(), $newIndex->getColumns());
            self::assertSame($index->isUnique(), $newIndex->isUnique());
        }
    }

    /**
     * @param list<string> $tables
     *
     * @return array<string, array<string, mixed>|false>
     */
    private static function rows(Connection $connection, array $tables, string $prefix): array
    {
        $rows = [];
        foreach ($tables as $table) {
            $rows[$table] = $connection->fetchAssociative('SELECT * FROM `' . $prefix . $table . '`');
        }

        return $rows;
    }

    private static function apply(Connection $connection, BackendbaseAbstractMigration $migration, string $direction): void
    {
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private static function fixture(): IsolatedMySql
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        return IsolatedMySql::create(false);
    }
}
