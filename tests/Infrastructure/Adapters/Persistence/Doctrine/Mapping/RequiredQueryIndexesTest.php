<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Mapping;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\ExampleTable;
use Backendbase\Migrations\Version20260823000000;
use Backendbase\Migrations\Version20260823215059;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Query\Query;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\Persistence\MessageRecoveryFixture;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function str_starts_with;

final class RequiredQueryIndexesTest extends TestCase
{
    private const array REQUIRED = [
        'example_groups_idx' => ['table' => 'example_table', 'columns' => ['type', 'type_target_id', 'deleted_at', 'lookup_group']],
        'example_group_entries_idx' => ['table' => 'example_table', 'columns' => ['type', 'type_target_id', 'lookup_group', 'deleted_at', 'created_at', 'id']],
        'integration_event_inbox_message_processed_idx' => ['table' => 'integration_event_inbox', 'columns' => ['message_id', 'processed_at']],
        'integration_event_delivery_failure_message_terminal_idx' => ['table' => 'integration_event_delivery_failure', 'columns' => ['message_id', 'dead_lettered_at']],
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $fixture          = MessageRecoveryFixture::create();
        $connection       = $fixture->connection();
        $this->connection = $connection;
        $connection->executeStatement('CREATE TABLE example_table (id BLOB PRIMARY KEY, type TEXT, type_target_id INTEGER, deleted_at TEXT, lookup_group TEXT, created_at TEXT)');
        $connection->insert('example_table', ['id' => 'entry', 'type' => 'system', 'lookup_group' => 'settings', 'created_at' => '2026-10-14 10:00:00']);
        foreach (self::REQUIRED as $name => $definition) {
            $connection->executeStatement('CREATE INDEX ' . $name . ' ON ' . $definition['table'] . ' (' . implode(', ', $definition['columns']) . ')');
        }
    }

    public function testMigrationIndexesHaveTheRequiredLeadingColumns(): void
    {
        foreach (self::REQUIRED as $name => $definition) {
            $this->assertIndex($name, $definition);
        }
    }

    public function testDoctrineMetadataMatchesTheExampleMigrationIndexes(): void
    {
        require 'config/doctrine-types.php';
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $manager  = new EntityManager($this->connection, $configuration);
        $metadata = $manager->getClassMetadata(ExampleTable::class);
        $indexes  = $metadata->table['indexes'] ?? [];

        foreach (['example_groups_idx', 'example_group_entries_idx'] as $name) {
            self::assertArrayHasKey($name, $indexes);
            self::assertSame(self::REQUIRED[$name]['columns'], $indexes[$name]['columns']);
        }
    }

    /** @param array<string, int|string> $parameters */
    #[DataProvider('queryPatterns')]
    public function testRequiredQueryPatternsCanUseTheirIndexes(string $sql, array $parameters, string $indexName): void
    {
        $connection = $this->connection;
        $rows       = $connection->fetchAllAssociative('EXPLAIN QUERY PLAN ' . $sql, $parameters);
        $details    = [];
        foreach ($rows as $row) {
            self::assertIsString($row['detail']);
            $details[] = $row['detail'];
        }

        $plan = implode("\n", $details);
        self::assertStringContainsString($indexName, $plan);
        self::assertStringNotContainsString('USE TEMP B-TREE', $plan);
    }

    /** @return iterable<string, array{string, array<string, int|string>, string}> */
    public static function queryPatterns(): iterable
    {
        $groups  = 'SELECT DISTINCT lookup_group FROM example_table WHERE type = :type AND ';
        $entries = 'SELECT id FROM example_table WHERE type = :type AND ';

        yield 'system groups' => [$groups . 'type_target_id IS NULL AND deleted_at IS NULL ORDER BY lookup_group LIMIT 10', ['type' => 'system'], 'example_groups_idx'];
        yield 'user groups' => [$groups . 'type_target_id = :target AND deleted_at IS NULL ORDER BY lookup_group LIMIT 10', ['type' => 'user', 'target' => 42], 'example_groups_idx'];
        yield 'system entries' => [$entries . 'type_target_id IS NULL AND lookup_group = :group AND deleted_at IS NULL ORDER BY created_at, id LIMIT 10', ['type' => 'system', 'group' => 'settings'], 'example_group_entries_idx'];
        yield 'user entries' => [$entries . 'type_target_id = :target AND lookup_group = :group AND deleted_at IS NULL ORDER BY created_at, id LIMIT 10', ['type' => 'user', 'target' => 42, 'group' => 'settings'], 'example_group_entries_idx'];
        yield 'unprocessed consumers' => ['SELECT 1 FROM integration_event_inbox WHERE message_id = :message AND processed_at IS NULL', ['message' => 'message'], 'integration_event_inbox_message_processed_idx'];
        yield 'nonterminal failures' => ['SELECT 1 FROM integration_event_delivery_failure WHERE message_id = :message AND dead_lettered_at IS NULL', ['message' => 'message'], 'integration_event_delivery_failure_message_terminal_idx'];
    }

    public function testFreshInstallStatementsIncludeEveryRequiredIndex(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $statements = [];
        foreach ([Version20260823000000::class, Version20260823215059::class] as $class) {
            $migration = new $class($connection, new NullLogger());
            self::assertFalse($migration->isTransactional());
            $migration->up(new Schema());
            $statements = [...$statements, ...array_values(array_map(static fn (Query $query): string => $query->getStatement(), $migration->getSql()))];
        }

        foreach (self::REQUIRED as $name => $definition) {
            $this->assertMigrationIndex($statements, $name, $definition);
        }
    }

    /**
     * @param list<string>                                $statements
     * @param array{table: string, columns: list<string>} $definition
     */
    private function assertMigrationIndex(array $statements, string $name, array $definition): void
    {
        $matches = array_values(array_filter($statements, static fn (string $statement): bool => str_starts_with($statement, 'CREATE TABLE ' . $definition['table'] . ' (')));
        self::assertCount(1, $matches);
        self::assertStringContainsString('INDEX ' . $name . ' (' . implode(', ', $definition['columns']) . ')', $matches[0]);
    }

    /** @param array{table: string, columns: list<string>} $definition */
    private function assertIndex(string $name, array $definition): void
    {
        $connection = $this->connection;
        $manager    = $connection->createSchemaManager();
        $indexes    = $manager->listTableIndexes($definition['table']);
        self::assertArrayHasKey($name, $indexes);
        self::assertSame($definition['columns'], $indexes[$name]->getColumns());
    }
}
