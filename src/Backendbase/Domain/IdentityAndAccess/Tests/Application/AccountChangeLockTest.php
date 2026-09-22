<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\RetireAccountHandler;
use Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\ReviseAccountHandler;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RetireAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\ReviseAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountChangeLockTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function accountChanges(): iterable
    {
        yield 'revision' => [false];
        yield 'retirement' => [true];
    }

    #[Test]
    #[DataProvider('accountChanges')]
    public function itLocksBeforeLoadingAndKeepsTheLockThroughRevocationAndSave(bool $retirement): void
    {
        $calls      = [];
        $accountId  = AccountId::fromString('7d9f6812-34f8-4bce-9396-82e97c9dd0ce');
        $account    = Account::register($accountId, new Email('account@example.com'), PasswordHash::create('hash'), new AccountPrivileges([]));
        $repository = $this->createStub(AccountWriteRepository::class);
        $repository->method('withAccountLock')->willReturnCallback(
            static function (AccountId $identifier, callable $change) use (&$calls, $accountId): void {
                self::assertSame($accountId, $identifier);
                $calls[] = 'lock';
                $change();
                $calls[] = 'unlock';
            },
        );
        $repository->method('getActive')->willReturnCallback(static function () use (&$calls, $account): Account {
            $calls[] = 'load-account';

            return $account;
        });
        $repository->method('save')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'save';
        });
        $authorizationState = $this->createStub(AccountAuthorizationState::class);
        $authorizationState->method('revokeAll')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'revoke';
        });
        $accessControl = new Acl(['account.revise', 'account.retire']);
        $command       = $retirement
            ? new RetireAccount($accountId, $accessControl)
            : new ReviseAccount($accountId, null, null, new AccountPrivileges([]), $accessControl);
        $handler       = $retirement
            ? new RetireAccountHandler($repository, $authorizationState)
            : new ReviseAccountHandler($repository, $authorizationState);

        $handler->handle($command);

        self::assertSame(['lock', 'load-account', 'revoke', 'save', 'unlock'], $calls);
    }
}
