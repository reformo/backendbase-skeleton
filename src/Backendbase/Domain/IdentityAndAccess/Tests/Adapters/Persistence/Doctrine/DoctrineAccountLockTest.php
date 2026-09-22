<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoctrineAccountLockTest extends TestCase
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
    public function itExcludesAccountChangesUntilAuthenticationCompletes(): void
    {
        $authenticationRepository = $this->fixture->authenticationRepository();
        $writeRepository          = $this->fixture->writeRepository(1);
        $accountId                = AccountLockFixture::accountId();
        $competingChange          = static function (): void {
            self::fail('A concurrent change must not enter the locked account.');
        };
        $compete                  = static fn () => $writeRepository->withAccountLock($accountId, $competingChange);
        $authenticate             = function () use ($compete): string {
            $this->assertLockContention($compete);

            return 'stored-token';
        };

        self::assertSame('stored-token', $authenticationRepository->withAuthenticationLock('locked@example.com', $authenticate));
        $connection = $this->fixture->connection();
        self::assertFalse($connection->isTransactionActive());
    }

    #[Test]
    public function itExcludesAuthenticationUntilRetirementCommits(): void
    {
        $writeRepository          = $this->fixture->writeRepository();
        $authenticationRepository = $this->fixture->authenticationRepository(1);
        $competingLogin           = static function (): string {
            self::fail('A concurrent login must not read the locked account.');
        };
        $compete                  = static fn (): string => $authenticationRepository->withAuthenticationLock('locked@example.com', $competingLogin);
        $retire                   = function () use ($compete, $writeRepository): void {
            $this->assertLockContention($compete);
            $account = $writeRepository->getActive(AccountLockFixture::accountId());
            $account->retire();
            $writeRepository->save($account);
        };

        $writeRepository->withAccountLock(AccountLockFixture::accountId(), $retire);
        $authenticate = static function () use ($authenticationRepository): string {
            self::assertNull($authenticationRepository->findByEmail('locked@example.com'));

            return 'retired-account-rejected';
        };
        self::assertSame('retired-account-rejected', $authenticationRepository->withAuthenticationLock('locked@example.com', $authenticate));
    }

    /** @param callable(): mixed $compete */
    private function assertLockContention(callable $compete): void
    {
        try {
            $compete();
            self::fail('A second connection must not acquire the account lock.');
        } catch (LockWaitTimeoutException) {
            self::addToAssertionCount(1);
        }
    }
}
