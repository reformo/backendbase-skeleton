<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Seeders\IdentityAndAccessPrivilegeSeeder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;

use function dirname;

final class IdentityAndAccessPrivilegeSeederTest extends TestCase
{
    private Connection $connection;

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
    }

    #[Test]
    public function itSeedsIdentityAndAccessPrivilegesIdempotently(): void
    {
        $seeder = new IdentityAndAccessPrivilegeSeeder();

        $seeder->seed($this->connection);
        $seeder->seed($this->connection);

        self::assertSame(7, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_privileges'));
        self::assertSame(
            [
                'account.list',
                'account.register',
                'account.retire',
                'account.revise',
                'example.add',
                'example.change',
                'example.remove',
            ],
            $this->connection->fetchFirstColumn('SELECT slug FROM example_privileges ORDER BY slug ASC'),
        );
    }
}
