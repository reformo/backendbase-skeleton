<?php

declare(strict_types=1);

namespace Tests\Shared\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\Entity\ExampleRecord;
use Backendbase\Shared\Migrations\BackendbaseAbstractMigration;
use Backendbase\Shared\Persistence\Doctrine\DQL\FirstFunction;
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
            dirname(__DIR__, 4)
                . '/src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $configuration->addCustomStringFunction(FirstFunction::FUNCTION_NAME, FirstFunction::class);
        $connection    = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $configuration,
        );
        $entityManager = new EntityManager($connection, $configuration);
        $dql           = 'SELECT example FROM ' . ExampleRecord::class . ' example '
            . 'WHERE example.id = FIRST(SELECT nested.id FROM ' . ExampleRecord::class . ' nested)';
        $sql           = $entityManager->createQuery($dql)->getSQL();
        self::assertIsString($sql);

        self::assertStringContainsString('LIMIT 1', $sql);
    }
}
