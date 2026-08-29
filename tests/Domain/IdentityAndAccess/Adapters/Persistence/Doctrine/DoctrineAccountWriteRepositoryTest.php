<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\AccountPersistenceMapper;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Domain\IdentityAndAccess\Exception\UnknownAccountPrivilege;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

final class DoctrineAccountWriteRepositoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE example_accounts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, email TEXT NOT NULL UNIQUE, '
            . 'password_hash TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE example_privileges ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, title TEXT NOT NULL, '
            . 'slug TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE example_account_privileged ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL, account_id INTEGER NOT NULL, '
            . 'privilege_id INTEGER NOT NULL, created_at TEXT NOT NULL, expired_at TEXT DEFAULT NULL, '
            . 'UNIQUE(account_id, privilege_id))',
        );
    }

    #[Test]
    public function itRegistersAnAccountAndItsActivePrivileges(): void
    {
        $this->insertPrivilege('account.register');
        $this->insertPrivilege('example.add');
        $repository = new DoctrineAccountWriteRepository($this->connection);

        $repository->register($this->account(['example.add', 'account.register']));

        $account = $repository->getActive($this->accountId());
        self::assertSame('account@example.com', $account->email()->toString());
        self::assertSame('password-hash', $account->passwordHash()->toString());
        self::assertSame(['account.register', 'example.add'], $account->privileges()->slugs());
    }

    #[Test]
    public function itRevisesAccountCredentialsAndReplacesActivePrivileges(): void
    {
        $this->insertPrivilege('account.list');
        $this->insertPrivilege('example.add');
        $repository = new DoctrineAccountWriteRepository($this->connection);
        $repository->register($this->account(['example.add', 'account.list']));
        $account = $repository->getActive($this->accountId());

        $account->revise(
            new Email('revised@example.com'),
            PasswordHash::create('revised-password-hash'),
            new AccountPrivileges(['account.list']),
        );
        $repository->save($account);

        $revised = $repository->getActive($this->accountId());
        self::assertSame('revised@example.com', $revised->email()->toString());
        self::assertSame('revised-password-hash', $revised->passwordHash()->toString());
        self::assertSame(['account.list'], $revised->privileges()->slugs());
        self::assertNotFalse($this->connection->fetchOne(
            'SELECT expired_at FROM example_account_privileged WHERE account_id = 1 AND privilege_id = 2',
        ));
    }

    #[Test]
    public function itRejectsAnUnavailablePrivilegeWithoutRegisteringTheAccount(): void
    {
        $repository = new DoctrineAccountWriteRepository($this->connection);

        try {
            $repository->register($this->account(['unknown.privilege']));
            self::fail('An unavailable privilege must fail registration.');
        } catch (UnknownAccountPrivilege) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_accounts'));
        }
    }

    #[Test]
    public function itRetiresAnActiveAccount(): void
    {
        $repository = new DoctrineAccountWriteRepository($this->connection);
        $repository->register($this->account([]));
        $repository->retire($repository->getActive($this->accountId()));

        $this->expectException(ResourceNotFound::class);

        $repository->getActive($this->accountId());
    }

    #[Test]
    public function itRejectsAnExistingAccountEmailBeforeRegistration(): void
    {
        $repository = new DoctrineAccountWriteRepository($this->connection);
        $repository->register($this->account([]));

        $this->expectException(AccountAlreadyRegistered::class);

        $repository->register($this->account([]));
    }

    #[Test]
    public function itTranslatesRegistrationConstraintViolations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('transactional')
            ->willThrowException($this->uniqueConstraintViolation());

        $this->expectException(AccountAlreadyRegistered::class);

        (new DoctrineAccountWriteRepository($connection))->register($this->account([]));
    }

    #[Test]
    public function itTranslatesRevisionConstraintViolations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('transactional')
            ->willThrowException($this->uniqueConstraintViolation());

        $this->expectException(AccountAlreadyRegistered::class);

        (new DoctrineAccountWriteRepository($connection))->save($this->account([]));
    }

    #[Test]
    public function itRejectsRetirementAndRevisionOfMissingAccounts(): void
    {
        $repository = new DoctrineAccountWriteRepository($this->connection);

        try {
            $repository->retire($this->account([]));
            self::fail('A missing account cannot be retired.');
        } catch (ResourceNotFound) {
            self::addToAssertionCount(1);
        }

        $this->expectException(ResourceNotFound::class);

        $repository->save($this->account([]));
    }

    #[Test]
    public function itRejectsInvalidPersistedAccountValues(): void
    {
        try {
            AccountPersistenceMapper::account([]);
            self::fail('A missing persisted account must fail.');
        } catch (UnexpectedValueException) {
            self::addToAssertionCount(1);
        }

        try {
            AccountPersistenceMapper::positiveInteger('0', 'id');
            self::fail('A zero persisted identifier must fail.');
        } catch (UnexpectedValueException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(UnexpectedValueException::class);

        AccountPersistenceMapper::account([
            [
                'uuid' => '',
                'email' => 'account@example.com',
                'password_hash' => 'password-hash',
                'privilege_slug' => null,
            ],
        ]);
    }

    /** @param list<string> $privileges */
    private function account(array $privileges): Account
    {
        return Account::register(
            $this->accountId(),
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges($privileges),
        );
    }

    private function accountId(): AccountId
    {
        return AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586');
    }

    private function insertPrivilege(string $slug): void
    {
        $this->connection->insert('example_privileges', [
            'uuid' => '5bb3fe29-8b80-463e-9d42-b3a9298a7586',
            'title' => $slug,
            'slug' => $slug,
            'created_at' => '2026-08-29 10:00:00.000000',
            'deleted_at' => null,
        ]);
    }

    private function uniqueConstraintViolation(): UniqueConstraintViolationException
    {
        $driverException = new class ('Unique constraint violation') extends RuntimeException implements DriverException {
            public function getSQLState(): string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverException, null);
    }
}
