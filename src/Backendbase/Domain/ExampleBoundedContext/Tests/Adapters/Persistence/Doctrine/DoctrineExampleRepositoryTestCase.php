<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;

use function dirname;

abstract class DoctrineExampleRepositoryTestCase extends TestCase
{
    protected Connection $connection;
    protected ExampleReadRepository $readRepository;
    protected ExampleWriteRepository $writeRepository;

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
        $this->readRepository  = new ExampleReadRepository($this->connection);
        $this->writeRepository = new ExampleWriteRepository($entityManager);
    }

    protected function readRepository(): ExampleReadRepositoryContract
    {
        return $this->readRepository;
    }

    protected function writeRepository(): ExampleWriteRepositoryContract
    {
        return $this->writeRepository;
    }
}
