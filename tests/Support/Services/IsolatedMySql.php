<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Assert;
use RuntimeException;

use function bin2hex;
use function filter_var;
use function getenv;
use function is_string;
use function preg_match;
use function random_bytes;

use const FILTER_VALIDATE_INT;

final readonly class IsolatedMySql
{
    private function __construct(private Connection $connection, private Configuration $configuration)
    {
    }

    public static function create(bool $withSchema = true): self
    {
        require 'config/doctrine-types.php';
        $port     = filter_var(getenv('BACKENDBASE_TEST_MYSQL_PORT') ?: '3306', FILTER_VALIDATE_INT);
        $password = getenv('BACKENDBASE_TEST_MYSQL_PASSWORD');
        if ($port === false || $port < 1 || $port > 65535 || $password === false || $password === '') {
            throw new RuntimeException('The isolated MySQL test connection is incomplete.');
        }

        $parameters = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => $port, 'user' => 'root', 'password' => $password, 'charset' => 'utf8mb4'];
        $root       = DriverManager::getConnection($parameters);
        Assert::assertStringStartsWith('8.4.', $root->getServerVersion());
        $database = 'backendbase_adr_' . bin2hex(random_bytes(8));
        $root->executeStatement('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci');
        $root->close();
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection([...$parameters, 'dbname' => $database]);
        $fixture    = new self($connection, $configuration);
        if ($withSchema) {
            $manager         = $fixture->manager();
            $metadataFactory = $manager->getMetadataFactory();
            new SchemaTool($manager)->createSchema($metadataFactory->getAllMetadata());
        }

        return $fixture;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function anotherConnection(): Connection
    {
        $connection = $this->connection;

        return DriverManager::getConnection($connection->getParams());
    }

    public function manager(Connection|null $connection = null): EntityManager
    {
        $connection ??= $this->connection;
        $platform     = $connection->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('datetime', Types::DATETIME_IMMUTABLE);

        return new EntityManager($connection, $this->configuration);
    }

    public function destroy(): void
    {
        $connection = $this->connection;
        $database   = $connection->getDatabase();
        if (! is_string($database) || preg_match('/\Abackendbase_adr_[0-9a-f]{16}\z/D', $database) !== 1) {
            throw new RuntimeException('Only this fixture database can be removed.');
        }

        $connection->executeStatement('DROP DATABASE ' . $database);
        $connection->close();
    }
}
