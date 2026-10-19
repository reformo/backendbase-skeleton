<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Migrations\Version20260823000000;
use Backendbase\Migrations\Version20260823215059;
use Backendbase\Migrations\Version20261014020000;
use Backendbase\Migrations\Version20261018000000;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Query\Query;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Services\IsolatedMySql;

use function getenv;
use function str_repeat;

final class MySqlQueryIndexesTest extends TestCase
{
    public function testFreshInstallIncludesRequiredIndexesOnMySql84(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $this->assertMigration($fixture->connection());
        } finally {
            $fixture->destroy();
        }
    }

    private function assertMigration(Connection $connection): void
    {
        foreach ([Version20260823000000::class, Version20260823215059::class, Version20261014020000::class, Version20261018000000::class] as $class) {
            $migration = new $class($connection, new NullLogger());
            self::assertFalse($migration->isTransactional());
            $migration->up(new Schema());
            $this->applySql($connection, $migration->getSql());
        }

        $connection->insert('example_table', ['id' => str_repeat('a', 16), 'type' => 'system', 'lookup_group' => 'settings', 'lookup_key' => 'flag', 'lookup_value' => 'enabled', 'details' => '{}', 'created_at' => '2026-10-14 10:00:00']);
        $manager = $connection->createSchemaManager();
        $indexes = $manager->listTableIndexes('example_table');
        self::assertArrayHasKey('example_groups_idx', $indexes);
        self::assertSame(['type', 'type_target_id', 'deleted_at', 'lookup_group'], $indexes['example_groups_idx']->getColumns());
        self::assertArrayHasKey('example_group_entries_idx', $indexes);
        self::assertSame(['type', 'type_target_id', 'lookup_group', 'deleted_at', 'created_at', 'id'], $indexes['example_group_entries_idx']->getColumns());
        self::assertArrayHasKey('integration_event_inbox_message_processed_idx', $manager->listTableIndexes('integration_event_inbox'));
        self::assertArrayHasKey('integration_event_delivery_failure_message_terminal_idx', $manager->listTableIndexes('integration_event_delivery_failure'));
        self::assertCount(11, $manager->listTableNames());
        self::assertArrayHasKey('http_request_replay_expiry_idx', $manager->listTableIndexes('http_request_replay'));
        self::assertNotContains('operator_action', $manager->listTableNames());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM example_table'));
    }

    /** @param array<int, Query> $queries */
    private function applySql(Connection $connection, array $queries): void
    {
        foreach ($queries as $query) {
            $statement = $query->getStatement();
            $connection->executeStatement($statement);
        }
    }
}
