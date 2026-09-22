<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticateAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Exception\InvalidCredentials;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameterValue;

use function password_hash;

use const PASSWORD_ARGON2ID;

final class AuthenticateAccountLockTest extends TestCase
{
    #[Test]
    public function itReadsAccountStateOnlyAfterAcquiringTheLock(): void
    {
        $lockAcquired = false;
        $staleAccount = $this->account();
        $repository   = $this->createStub(AccountAuthenticationRepository::class);
        $repository->method('withAuthenticationLock')->willReturnCallback(
            static function (string $email, callable $authenticate) use (&$lockAcquired): string {
                $lockAcquired = true;

                return $authenticate();
            },
        );
        $repository->method('findByEmail')->willReturnCallback(
            static function () use (&$lockAcquired, $staleAccount): AccountAuthentication|null {
                // Retirement completes before this login obtains the account lock.
                return $lockAcquired ? null : $staleAccount;
            },
        );
        $issuer = $this->createMock(TokenIssuer::class);
        $issuer->expects(self::never())->method('issueNewToken');

        $this->expectException(InvalidCredentials::class);

        (new AuthenticateAccount($repository, $issuer))->authenticate(
            'account@example.com',
            new SensitiveParameterValue('correct-password'),
        );
    }

    #[Test]
    public function itKeepsTheLockUntilTokenStorageCompletes(): void
    {
        $calls      = [];
        $repository = $this->createStub(AccountAuthenticationRepository::class);
        $repository->method('findByEmail')->willReturnCallback(function () use (&$calls): AccountAuthentication {
            $calls[] = 'read-account';

            return $this->account();
        });
        $repository->method('withAuthenticationLock')->willReturnCallback(
            static function (string $email, callable $authenticate) use (&$calls): string {
                $calls[] = 'lock';
                $token   = $authenticate();
                $calls[] = 'unlock';

                return $token;
            },
        );
        $issuer = $this->createStub(TokenIssuer::class);
        $issuer->method('issueNewToken')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'store-token';

            return 'test-token';
        });

        $token = (new AuthenticateAccount($repository, $issuer))->authenticate(
            'account@example.com',
            new SensitiveParameterValue('correct-password'),
        );

        self::assertSame('test-token', $token);
        self::assertSame(['lock', 'read-account', 'store-token', 'unlock'], $calls);
    }

    private function account(): AccountAuthentication
    {
        return new AccountAuthentication(
            '7d9f6812-34f8-4bce-9396-82e97c9dd0ce',
            'account@example.com',
            password_hash('correct-password', PASSWORD_ARGON2ID),
            ['account.list'],
        );
    }
}
