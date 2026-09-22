<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Shared\Primitives\Email;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DoctrineAccountLockFailureTest extends TestCase
{
    private AccountLockFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AccountLockFixture();
    }

    protected function tearDown(): void
    {
        $this->fixture->close();
    }

    #[Test]
    public function itRollsBackChangesAndClosesTheFailedUnitOfWork(): void
    {
        $repository = $this->fixture->writeRepository();
        $change     = static function () use ($repository): void {
            $account = $repository->getActive(AccountLockFixture::accountId());
            $account->revise(new Email('changed@example.com'), null, null);
            $repository->save($account);

            throw new RuntimeException('The account change failed after persistence.');
        };

        try {
            $repository->withAccountLock(AccountLockFixture::accountId(), $change);
            self::fail('The account change must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('The account change failed after persistence.', $exception->getMessage());
        }

        $authenticationRepository = $this->fixture->authenticationRepository(1);
        self::assertNotNull($authenticationRepository->findByEmail('locked@example.com'));
        self::assertNull($authenticationRepository->findByEmail('changed@example.com'));
        $manager = $this->fixture->manager();
        self::assertFalse($manager->isOpen());
    }

    #[Test]
    public function itReleasesTheLockWhenTokenStorageFails(): void
    {
        $repository   = $this->fixture->authenticationRepository();
        $authenticate = static function (): string {
            throw new RuntimeException('Token storage failed.');
        };

        try {
            $repository->withAuthenticationLock('locked@example.com', $authenticate);
            self::fail('Token storage must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Token storage failed.', $exception->getMessage());
        }

        $writeRepository = $this->fixture->writeRepository(1);
        $changed         = false;
        $change          = static function () use (&$changed): void {
            $changed = true;
        };
        $writeRepository->withAccountLock(AccountLockFixture::accountId(), $change);
        self::assertTrue($changed);
    }

    #[Test]
    public function itRejectsAnExistingTransactionSnapshot(): void
    {
        $connection = $this->fixture->connection();
        $connection->beginTransaction();
        $repository   = $this->fixture->authenticationRepository();
        $authenticate = static function (): string {
            self::fail('Authentication must not use an existing transaction snapshot.');
        };

        try {
            $this->expectException(LogicException::class);
            $repository->withAuthenticationLock('locked@example.com', $authenticate);
        } finally {
            $connection->rollBack();
        }
    }

    #[Test]
    public function itRefreshesAccountAndPrivilegeStateAfterLockAcquisition(): void
    {
        $repository = $this->fixture->writeRepository();
        $repository->getActive(AccountLockFixture::accountId());
        $connection = $this->fixture->connection(1);
        $connection->executeStatement('UPDATE example_accounts SET email = :email', ['email' => 'changed@example.com']);
        $connection->executeStatement('UPDATE example_account_privileged SET expired_at = :expiredAt', ['expiredAt' => '2026-09-07 10:00:00']);
        $verify = static function () use ($repository): void {
            $account    = $repository->getActive(AccountLockFixture::accountId());
            $privileges = $account->privileges();
            self::assertSame([], $privileges->slugs());
            $email = $account->email();
            self::assertSame('changed@example.com', $email->toString());
        };

        $repository->withAccountLock(AccountLockFixture::accountId(), $verify);
    }
}
