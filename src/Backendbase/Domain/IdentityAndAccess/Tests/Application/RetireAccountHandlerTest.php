<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\RetireAccountHandler;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RetireAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetireAccountHandlerTest extends TestCase
{
    #[Test]
    public function itRetiresAnActiveAccountAfterAclAuthorization(): void
    {
        $accountId     = AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586');
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(RetireAccountHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $account    = Account::register(
            $accountId,
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges([]),
        );
        $repository = $this->createMock(AccountWriteRepository::class);
        $repository->expects(self::once())->method('withAccountLock')->willReturnCallback(
            static function (AccountId $accountId, callable $change): void {
                $change();
            },
        );
        $repository->expects(self::once())->method('getActive')->with($accountId)->willReturn($account);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Account $retired): bool => $retired->isRetired()));
        $authorizationState = $this->createMock(AccountAuthorizationState::class);
        $authorizationState->expects(self::once())->method('revokeAll')->with($accountId);

        (new RetireAccountHandler($repository, $authorizationState))->handle(
            new RetireAccount($accountId, $accessControl),
        );
    }

    #[Test]
    public function itStopsRetirementWhenAuthorizationRevocationFails(): void
    {
        $accountId     = AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586');
        $accessControl = $this->createStub(AccessControl::class);
        $accessControl->method('isAllowed')->willReturn(true);
        $account    = Account::register(
            $accountId,
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges([]),
        );
        $repository = $this->createMock(AccountWriteRepository::class);
        $repository->expects(self::once())->method('withAccountLock')->willReturnCallback(
            static function (AccountId $accountId, callable $change): void {
                $change();
            },
        );
        $repository->method('getActive')->willReturn($account);
        $repository->expects(self::never())->method('save');
        $authorizationState = $this->createStub(AccountAuthorizationState::class);
        $authorizationState->method('revokeAll')->willThrowException(new RuntimeException('Redis unavailable.'));

        try {
            (new RetireAccountHandler($repository, $authorizationState))->handle(
                new RetireAccount($accountId, $accessControl),
            );
            self::fail('A revocation failure must stop account retirement.');
        } catch (RuntimeException) {
            self::assertFalse($account->isRetired());
        }
    }
}
