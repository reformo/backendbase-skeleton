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

final class AuthenticateAccountTest extends TestCase
{
    #[Test]
    public function itIssuesATokenWithTheAccountPrivileges(): void
    {
        $account    = new AccountAuthentication(
            '7d9f6812-34f8-4bce-9396-82e97c9dd0ce',
            'account@example.com',
            password_hash('correct-password', PASSWORD_ARGON2ID),
            ['example.add', 'example.remove'],
        );
        $repository = $this->createMock(AccountAuthenticationRepository::class);
        $repository->expects(self::once())
            ->method('findByEmail')
            ->with('account@example.com')
            ->willReturn($account);
        $tokenIssuer = $this->createMock(TokenIssuer::class);
        $tokenIssuer->expects(self::once())
            ->method('issueNewToken')
            ->with('userId', $account->uuid(), [
                'uuid' => $account->uuid(),
                'email' => $account->email(),
                'privileges' => $account->privileges(),
            ])
            ->willReturn('access-token');

        $accessToken = (new AuthenticateAccount($repository, $tokenIssuer))->authenticate(
            $account->email(),
            new SensitiveParameterValue('correct-password'),
        );

        self::assertSame('access-token', $accessToken);
    }

    #[Test]
    public function itRejectsUnknownAccountsWithoutIssuingAToken(): void
    {
        $repository = $this->createStub(AccountAuthenticationRepository::class);
        $repository->method('findByEmail')->willReturn(null);
        $tokenIssuer = $this->createMock(TokenIssuer::class);
        $tokenIssuer->expects(self::never())->method('issueNewToken');

        $this->expectException(InvalidCredentials::class);

        (new AuthenticateAccount($repository, $tokenIssuer))->authenticate(
            'missing@example.com',
            new SensitiveParameterValue('correct-password'),
        );
    }

    #[Test]
    public function itRejectsAnInvalidPasswordWithoutIssuingAToken(): void
    {
        $account    = new AccountAuthentication(
            '7d9f6812-34f8-4bce-9396-82e97c9dd0ce',
            'account@example.com',
            password_hash('correct-password', PASSWORD_ARGON2ID),
            [],
        );
        $repository = $this->createStub(AccountAuthenticationRepository::class);
        $repository->method('findByEmail')->willReturn($account);
        $tokenIssuer = $this->createMock(TokenIssuer::class);
        $tokenIssuer->expects(self::never())->method('issueNewToken');

        $this->expectException(InvalidCredentials::class);

        (new AuthenticateAccount($repository, $tokenIssuer))->authenticate(
            $account->email(),
            new SensitiveParameterValue('invalid-password'),
        );
    }
}
