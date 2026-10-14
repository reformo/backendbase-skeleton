<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Migrations;

use Backendbase\Migrations\Version20260823215059;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ExampleSchemaMigrationTest extends TestCase
{
    #[Test]
    public function itCreatesTheFinalExampleSchemaInOneStatement(): void
    {
        $migration = $this->migration(new MySQLPlatform());
        self::assertFalse($migration->isTransactional());
        self::assertNotSame('', $migration->getDescription());
        $migration->up(new Schema());
        $queries = $migration->getSql();
        self::assertCount(1, $queries);
        $statement = $queries[0]->getStatement();
        self::assertStringStartsWith('CREATE TABLE example_table (', $statement);
        self::assertStringContainsString(
            'normalized_type_target_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(type_target_id, 0)) STORED',
            $statement,
        );
        self::assertStringContainsString(
            'active_uniqueness_key TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED',
            $statement,
        );
        self::assertStringContainsString(
            'UNIQUE INDEX example_active_data_unq '
                . '(type, normalized_type_target_id, lookup_group, lookup_key, active_uniqueness_key)',
            $statement,
        );
        self::assertStringContainsString('id BINARY(16) NOT NULL', $statement);
        self::assertStringContainsString('BIN_TO_UUID(id, 0)) VIRTUAL', $statement);
        self::assertStringContainsString('version INT DEFAULT 1 NOT NULL', $statement);
        self::assertStringContainsString('created_at DATETIME(6) NOT NULL', $statement);
        self::assertStringNotContainsString('AUTO_INCREMENT', $statement);
        self::assertStringNotContainsString('example_uuid_unq', $statement);
        self::assertStringContainsString(
            'INDEX example_search_idx (type, type_target_id, lookup_group, lookup_key, is_active, deleted_at)',
            $statement,
        );
        self::assertStringContainsString(
            'INDEX example_relation_idx (type, type_target_id, lookup_key, deleted_at)',
            $statement,
        );
        self::assertStringNotContainsString('example_data_unq', $statement);
        self::assertStringContainsString('INDEX example_groups_idx (type, type_target_id, deleted_at, lookup_group)', $statement);
        self::assertStringContainsString('INDEX example_group_entries_idx (type, type_target_id, lookup_group, deleted_at, created_at, id)', $statement);
        self::assertStringEndsWith(') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci ENGINE = InnoDB', $statement);
    }

    #[Test]
    public function itDropsTheExampleTableOnRollback(): void
    {
        $migration = $this->migration(new MySQLPlatform());
        $migration->down(new Schema());
        $queries = $migration->getSql();
        self::assertCount(1, $queries);
        self::assertSame('DROP TABLE example_table', $queries[0]->getStatement());
    }

    #[Test]
    #[DataProvider('unsupportedOperations')]
    public function itRejectsUnsupportedDatabasePlatforms(string $operation): void
    {
        $migration = $this->migration(new SQLitePlatform());
        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage('This migration supports MySQL only.');

        $migration->{$operation}(new Schema());
    }

    /** @return array<string, array{string}> */
    public static function unsupportedOperations(): array
    {
        return ['create' => ['up'], 'rollback' => ['down']];
    }

    private function migration(AbstractPlatform $platform): Version20260823215059
    {
        $connection            = $this->createMock(Connection::class);
        $platformConfiguration = $connection->method('getDatabasePlatform');
        $platformConfiguration->willReturn($platform);
        $queryExpectation = $connection->expects(self::never());
        $queryExpectation->method('fetchOne');

        return new Version20260823215059($connection, new NullLogger());
    }
}
