<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\EntryRecord;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DQL\FirstFunction;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Doctrine\UuidType;

use function dirname;

final class DqlAndMigrationTest extends TestCase
{
    #[Test]
    public function itMarksBackendbaseMigrationsAsNonTransactional(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $migration  = new class ($connection, new NullLogger()) extends BackendbaseAbstractMigration {
            public function up(Schema $schema): void
            {
            }
        };

        self::assertFalse($migration->isTransactional());
    }

    #[Test]
    public function itLimitsAFirstSubqueryToOneRow(): void
    {
        if (! Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 5)
                . '/src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Doctrine/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $configuration->addCustomStringFunction(FirstFunction::FUNCTION_NAME, FirstFunction::class);
        $connection    = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $configuration,
        );
        $entityManager = new EntityManager($connection, $configuration);
        $dql           = 'SELECT example FROM ' . EntryRecord::class . ' example '
            . 'WHERE example.id = FIRST(SELECT nested.id FROM ' . EntryRecord::class . ' nested)';
        $sql           = $entityManager->createQuery($dql)->getSQL();
        self::assertIsString($sql);

        self::assertStringContainsString('LIMIT 1', $sql);
    }
}
