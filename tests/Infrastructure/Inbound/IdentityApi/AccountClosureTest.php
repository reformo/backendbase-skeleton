<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationClock;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountClosureTest extends TestCase
{
    #[Test]
    public function itDeniesAnAlreadyIssuedTokenAfterAccountClosure(): void
    {
        $fixture   = IdentityFixture::create();
        $user      = IdentityScenario::enrolled($fixture);
        $container = $fixture->container();
        $principal = IdentityScenario::principal($fixture, $user['secret']);
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $token     = $issuer->issue($principal, $clock->sessionExpiresAt());
        $api       = IdentityApiFixture::create($fixture, Realm::USER);
        self::assertSame(200, $api->request('DELETE', '/identity/account', token: $token)->getStatusCode());
        self::assertSame(401, $api->request('GET', '/identity', token: $token)->getStatusCode());
        self::assertSame(401, $api->request('POST', '/auth/login', ['loginName' => 'ada.user', 'password' => 'valid-password-123'])->getStatusCode());
    }
}
