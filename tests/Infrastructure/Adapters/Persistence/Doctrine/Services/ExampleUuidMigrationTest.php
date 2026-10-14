<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\ExampleTable;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use Backendbase\Migrations\Version20260823215059;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Query\Query;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Tests\Support\Services\IsolatedMySql;

use function getenv;

final class ExampleUuidMigrationTest extends TestCase
{
    public function testFreshSchemaSupportsBinaryIdentifiersAndOrmRoundTrips(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            $migration  = new Version20260823215059($connection, new NullLogger());
            $migration->up(new Schema());
            self::executeMigration($connection, $migration->getSql());
            $existingId = Uuid::uuid4();
            $connection->insert('example_table', ['id' => $existingId->getBytes(), 'type' => 'user', 'type_target_id' => 123, 'lookup_group' => 'legacy', 'lookup_key' => 'preserved', 'lookup_value' => 'Existing value', 'details' => '{"legacy":true}', 'is_active' => 1, 'created_at' => '2026-08-31 09:00:00']);
            $row = $connection->fetchAssociative('SELECT id, uuid, type_target_id, lookup_value FROM example_table');
            self::assertIsArray($row);
            self::assertSame($existingId->getBytes(), $row['id']);
            self::assertSame($existingId->toString(), $row['uuid']);
            self::assertSame(123, $row['type_target_id']);
            self::assertSame('Existing value', $row['lookup_value']);
            $schemaManager = $connection->createSchemaManager();
            self::assertSame(['example_table'], $schemaManager->listTableNames());
            $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Doctrine/Entity'], true);
            $configuration->enableNativeLazyObjects(true);
            $manager               = new EntityManagerFactory($configuration)->create($connection);
            $databaseConfiguration = $connection->getConfiguration();
            $databaseConfiguration->setSchemaAssetsFilter(static fn (string $name): bool => $name === 'example_table');
            $metadataFactory = $manager->getMetadataFactory();
            self::assertSame([], new SchemaTool($manager)->getUpdateSchemaSql($metadataFactory->getAllMetadata()));
            self::assertSame([6, 6, 6], $connection->fetchFirstColumn("SELECT datetime_precision FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'example_table' AND data_type = 'datetime' ORDER BY column_name"));
            $repository = new EntryWriteRepository($manager);
            self::assertSame($existingId->toString(), $repository->getActive($existingId->toString())->id());
            $id    = Uuid::uuid7();
            $entry = Entry::create($id->toString(), EntryType::SYSTEM, null, 'new', true, 'new-key', 'New value', []);
            $repository->add($entry);
            $manager->clear();
            $record = $manager->find(ExampleTable::class, $id);
            self::assertInstanceOf(ExampleTable::class, $record);
            self::assertSame($id->toString(), $record->id());
            self::assertSame($id->toString(), $record->toDomain()->id());
            self::assertSame([], new SchemaTool($manager)->getUpdateSchemaSql($metadataFactory->getAllMetadata()));
        } finally {
            $fixture->destroy();
        }
    }

    /** @param array<Query> $queries */
    private static function executeMigration(Connection $connection, array $queries): void
    {
        foreach ($queries as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }
}
