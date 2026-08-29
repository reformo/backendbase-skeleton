<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\RegisterAccountHandler;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RegisterAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegisterAccountHandlerTest extends TestCase
{
    #[Test]
    public function itRegistersAnAccountAfterAclAuthorization(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(RegisterAccountHandler::REQUIRED_PRIVILEGE)
            ->willReturn(true);
        $repository = $this->createMock(AccountWriteRepository::class);
        $repository->expects(self::once())
            ->method('register')
            ->with(self::callback(static function (Account $account): bool {
                return $account->email()->toString() === 'account@example.com'
                    && $account->privileges()->slugs() === ['example.add'];
            }));

        (new RegisterAccountHandler($repository))->handle($this->command($accessControl));
    }

    #[Test]
    public function itDoesNotRegisterAnAccountWithoutTheRequiredPrivilege(): void
    {
        $accessControl = $this->createMock(AccessControl::class);
        $accessControl->expects(self::once())
            ->method('isAllowed')
            ->with(RegisterAccountHandler::REQUIRED_PRIVILEGE)
            ->willThrowException(ResourceAccessForbidden::create('Forbidden.'));
        $repository = $this->createMock(AccountWriteRepository::class);
        $repository->expects(self::never())->method('register');

        $this->expectException(ResourceAccessForbidden::class);

        (new RegisterAccountHandler($repository))->handle($this->command($accessControl));
    }

    private function command(AccessControl $accessControl): RegisterAccount
    {
        return new RegisterAccount(
            AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586'),
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges(['example.add']),
            $accessControl,
        );
    }
}
