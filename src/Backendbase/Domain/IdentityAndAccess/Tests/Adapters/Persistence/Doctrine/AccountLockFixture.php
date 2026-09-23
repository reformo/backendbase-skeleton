<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Seeders\IdentityAndAccessPrivilegeSeeder;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Doctrine\UuidType;
use RuntimeException;

use function dirname;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class AccountLockFixture
{
    /** @var array<int, EntityManager> */
    private array $sessions = [];
    private string $databaseFile;

    public function __construct()
    {
        $databaseFile = tempnam(sys_get_temp_dir(), 'account-lock-');
        if ($databaseFile === false) {
            throw new RuntimeException('Cannot create the isolated account database.');
        }

        $this->databaseFile = $databaseFile;
        if (! Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        $this->sessions = [$this->createSession(), $this->createSession()];
        $manager        = $this->manager();
        $metadata       = $manager->getMetadataFactory();
        $mappedClasses  = $metadata->getAllMetadata();
        $schemaTool     = new SchemaTool($manager);
        $schemaTool->createSchema($mappedClasses);
        $connection = $this->connection();
        (new IdentityAndAccessPrivilegeSeeder())->seed($connection);
        $repository = $this->writeRepository();
        $repository->register(Account::register(
            self::accountId(),
            new Email('locked@example.com'),
            PasswordHash::create('test-password-hash'),
            new AccountPrivileges(['account.list']),
        ));
    }

    public static function accountId(): AccountId
    {
        return AccountId::fromString('7d9f6812-34f8-4bce-9396-82e97c9dd0ce');
    }

    public function manager(int $session = 0): EntityManager
    {
        return $this->sessions[$session];
    }

    public function connection(int $session = 0): Connection
    {
        $manager = $this->manager($session);

        return $manager->getConnection();
    }

    public function authenticationRepository(int $session = 0): DoctrineAccountAuthenticationRepository
    {
        return new DoctrineAccountAuthenticationRepository($this->connection($session));
    }

    public function writeRepository(int $session = 0): DoctrineAccountWriteRepository
    {
        return new DoctrineAccountWriteRepository($this->manager($session));
    }

    public function close(): void
    {
        foreach ($this->sessions as $manager) {
            $connection = $manager->getConnection();
            $connection->close();
        }

        // tempnam created this isolated test database file.
        // nosemgrep: php.lang.security.unlink-use.unlink-use
        unlink($this->databaseFile);
    }

    private function createSession(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 4) . '/Adapters/Persistence/Doctrine/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->databaseFile]);
        $connection->executeStatement('PRAGMA busy_timeout = 0');

        return new EntityManager($connection, $configuration);
    }
}
