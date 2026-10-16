<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountClosureTest extends TestCase
{
    #[DataProvider('realms')]
    public function testAccountClosureDeniesExistingTokensAndLoginInBothRealms(Realm $realm, Realm $otherRealm): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $token      = AdminContactVerificationTest::token($fixture, $realm);
        $otherToken = AdminContactVerificationTest::token($fixture, $otherRealm);
        $api        = IdentityApiFixture::create($fixture, $realm);
        $otherApi   = IdentityApiFixture::create($fixture, $otherRealm);
        $response   = $api->request('GET', '/identity', token: $token);
        self::assertSame(200, $response->getStatusCode());
        $response = $otherApi->request('GET', '/identity', token: $otherToken);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('DELETE', '/identity/account', token: $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], UserFlowTest::resource($response, 'account'));
        $response = $api->request('GET', '/identity', token: $token);
        self::assertSame(401, $response->getStatusCode());
        $response = $otherApi->request('GET', '/identity', token: $otherToken);
        self::assertSame(401, $response->getStatusCode());
        $login    = ['identifier' => 'admin@example.com', 'password' => 'valid-password-123'];
        $response = $api->request('POST', '/auth/login', $login);
        self::assertSame(401, $response->getStatusCode());
        $response = $otherApi->request('POST', '/auth/login', $login);
        self::assertSame(401, $response->getStatusCode());
    }

    /** @return array<string, array{Realm, Realm}> */
    public static function realms(): array
    {
        return ['user' => [Realm::USER, Realm::ADMIN], 'admin' => [Realm::ADMIN, Realm::USER]];
    }
}
