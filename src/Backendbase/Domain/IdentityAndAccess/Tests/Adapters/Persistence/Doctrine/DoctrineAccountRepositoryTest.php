<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\AccountPrivilegeRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\AccountRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\PrivilegeRecord;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\UnknownAccountPrivilege;
use Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\AccountRepositoryContract;
use Backendbase\Seeders\IdentityAndAccessPrivilegeSeeder;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Doctrine\UuidType;

use function dirname;

final class DoctrineAccountRepositoryTest extends AccountRepositoryContract
{
    private Connection $connection;
    private EntityManager $entityManager;
    private DoctrineAccountAuthenticationRepository $authenticationRepository;
    private DoctrineAccountReadRepository $readRepository;
    private DoctrineAccountWriteRepository $writeRepository;

    protected function setUp(): void
    {
        if (! Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 4) . '/Adapters/Persistence/Doctrine/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $this->connection    = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->entityManager = new EntityManager($this->connection, $configuration);
        $schemaTool          = new SchemaTool($this->entityManager);
        $metadata            = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
        (new IdentityAndAccessPrivilegeSeeder())->seed($this->connection);

        $this->authenticationRepository = new DoctrineAccountAuthenticationRepository($this->connection);
        $this->readRepository           = new DoctrineAccountReadRepository($this->connection);
        $this->writeRepository          = new DoctrineAccountWriteRepository($this->entityManager);
    }

    #[Test]
    public function itRejectsAnUnavailablePrivilege(): void
    {
        $account = $this->account();
        $account->revise(null, null, new AccountPrivileges(['unknown.privilege']));

        $this->expectException(UnknownAccountPrivilege::class);

        $this->writeRepository->register($account);
    }

    #[Test]
    public function itMapsGeneratedRecordIdentifiers(): void
    {
        $this->writeRepository->register($this->account());
        $accountRecord   = $this->entityManager->getRepository(AccountRecord::class)->findOneBy([]);
        $privilegeRecord = $this->entityManager->getRepository(PrivilegeRecord::class)->findOneBy(['slug' => 'account.list']);
        $grantRecord     = $this->entityManager->getRepository(AccountPrivilegeRecord::class)->findOneBy([]);

        self::assertInstanceOf(AccountRecord::class, $accountRecord);
        self::assertInstanceOf(PrivilegeRecord::class, $privilegeRecord);
        self::assertInstanceOf(AccountPrivilegeRecord::class, $grantRecord);
        self::assertGreaterThan(0, $accountRecord->id());
        self::assertGreaterThan(0, $privilegeRecord->id());
        self::assertGreaterThan(0, $grantRecord->id());
    }

    #[Test]
    public function itRegistersAnAccountWithoutPrivileges(): void
    {
        $account = Account::register(
            AccountId::fromString('6bb3fe29-8b80-463e-9d42-b3a9298a7586'),
            new Email('empty@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges([]),
        );

        $this->writeRepository->register($account);

        self::assertSame([], $this->writeRepository->getActive($account->id())->privileges()->slugs());
    }

    protected function authenticationRepository(): AccountAuthenticationRepository
    {
        return $this->authenticationRepository;
    }

    protected function readRepository(): AccountReadRepository
    {
        return $this->readRepository;
    }

    protected function writeRepository(): AccountWriteRepository
    {
        return $this->writeRepository;
    }
}
