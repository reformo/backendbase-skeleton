<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\ReviseAccountHandler;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\ReviseAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReviseAccountHandlerTest extends TestCase
{
    #[Test]
    public function itRevisesAnActiveAccountAfterAclAuthorization(): void
    {
        $accountId     = AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586');
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(ReviseAccountHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $account    = $this->account($accountId);
        $repository = $this->createMock(AccountWriteRepository::class);
        $repository->expects(self::once())->method('withAccountLock')->willReturnCallback(
            static function (AccountId $accountId, callable $change): void {
                $change();
            },
        );
        $repository->expects(self::once())->method('getActive')->with($accountId)->willReturn($account);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Account $revised): bool {
                return $revised->email()->toString() === 'revised@example.com'
                    && $revised->privileges()->slugs() === ['account.list'];
            }));
        $authorizationState = $this->createMock(AccountAuthorizationState::class);
        $authorizationState->expects(self::once())->method('revokeAll')->with($accountId);

        (new ReviseAccountHandler($repository, $authorizationState))->handle(new ReviseAccount(
            $accountId,
            new Email('revised@example.com'),
            PasswordHash::create('revised-password-hash'),
            new AccountPrivileges(['account.list']),
            $accessControl,
        ));
    }

    private function account(AccountId $accountId): Account
    {
        return Account::register(
            $accountId,
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges(['example.add']),
        );
    }
}
