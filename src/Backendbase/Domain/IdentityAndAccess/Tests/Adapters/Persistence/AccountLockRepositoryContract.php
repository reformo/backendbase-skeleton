<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence;

use Backendbase\Shared\Primitives\Email;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

trait AccountLockRepositoryContract
{
    #[Test]
    public function itReturnsTheAuthenticationCallbackResult(): void
    {
        $writeRepository = $this->writeRepository();
        $writeRepository->register($this->account());
        $repository   = $this->authenticationRepository();
        $authenticate = static function () use ($repository): string {
            $account = $repository->findByEmail('account@example.com');
            self::assertNotNull($account);

            return $account->email();
        };

        self::assertSame('account@example.com', $repository->withAuthenticationLock('account@example.com', $authenticate));
    }

    #[Test]
    public function itCommitsACompleteAccountChange(): void
    {
        $repository = $this->writeRepository();
        $repository->register($this->account());
        $accountId = $this->accountId();
        $change    = static function () use ($repository, $accountId): void {
            $account = $repository->getActive($accountId);
            $account->revise(new Email('changed@example.com'), null, null);
            $repository->save($account);
        };

        $repository->withAccountLock($accountId, $change);
        $authenticationRepository = $this->authenticationRepository();
        self::assertNotNull($authenticationRepository->findByEmail('changed@example.com'));
    }

    #[Test]
    public function itRollsBackAnIncompleteAccountChange(): void
    {
        $repository = $this->writeRepository();
        $repository->register($this->account());
        $accountId = $this->accountId();
        $change    = static function () use ($repository, $accountId): void {
            $account = $repository->getActive($accountId);
            $account->revise(new Email('changed@example.com'), null, null);
            $repository->save($account);

            throw new RuntimeException('Incomplete account change.');
        };

        try {
            $repository->withAccountLock($accountId, $change);
            self::fail('The incomplete account change must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Incomplete account change.', $exception->getMessage());
        }

        $authenticationRepository = $this->authenticationRepository();
        self::assertNotNull($authenticationRepository->findByEmail('account@example.com'));
        self::assertNull($authenticationRepository->findByEmail('changed@example.com'));
    }
}
