<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository as EntryReadRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository as EntryWriteRepositoryContract;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Pdo\Sqlite;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function assert;
use function dirname;
use function str_replace;

abstract class DoctrineEntryRepositoryTestCase extends TestCase
{
    protected Connection $connection;
    protected EntryReadRepository $readRepository;
    protected EntryWriteRepository $writeRepository;

    protected function setUp(): void
    {
        require 'config/doctrine-types.php';
        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 4) . '/Adapters/Persistence/Doctrine/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $nativeConnection = $this->connection->getNativeConnection();
        assert($nativeConnection instanceof Sqlite);
        $nativeConnection->createFunction('BIN_TO_UUID', static function (string $bytes, int $swap): string {
            $uuid = Uuid::fromBytes($bytes);

            return $uuid->toString();
        }, 2, Sqlite::DETERMINISTIC);
        $entityManager   = new EntityManager($this->connection, $configuration);
        $schemaTool      = new SchemaTool($entityManager);
        $metadataFactory = $entityManager->getMetadataFactory();
        foreach ($schemaTool->getCreateSchemaSql($metadataFactory->getAllMetadata()) as $statement) {
            $this->connection->executeStatement(str_replace('CHAR(36) CHARACTER SET ascii COLLATE ascii_bin', 'TEXT', $statement));
        }

        $this->readRepository  = new EntryReadRepository($this->connection);
        $this->writeRepository = new EntryWriteRepository($entityManager);
    }

    protected function readRepository(): EntryReadRepositoryContract
    {
        return $this->readRepository;
    }

    protected function writeRepository(): EntryWriteRepositoryContract
    {
        return $this->writeRepository;
    }
}
