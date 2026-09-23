<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository as EntryReadRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository as EntryWriteRepositoryContract;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;

use function dirname;

abstract class DoctrineEntryRepositoryTestCase extends TestCase
{
    protected Connection $connection;
    protected EntryReadRepository $readRepository;
    protected EntryWriteRepository $writeRepository;

    protected function setUp(): void
    {
        if (! Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 4) . '/Adapters/Persistence/Doctrine/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $entityManager    = new EntityManager($this->connection, $configuration);
        $schemaTool       = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
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
