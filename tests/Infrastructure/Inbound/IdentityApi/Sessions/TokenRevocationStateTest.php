<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class TokenRevocationStateTest extends TestCase
{
    #[DataProvider('apis')]
    public function testTokenOnlyRevocationPreservesRenewalUntilLogout(Realm $realm, bool $example): void
    {
        [, $api, $session] = SessionHttpScenario::login($realm, $example);
        $access            = UserFlowTest::text($session, 'accessToken');
        $refresh           = UserFlowTest::text($session, 'refreshToken');
        $response          = $api->request('DELETE', '/identity/token', token: $access);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('GET', '/identity', token: $access);
        self::assertSame(401, $response->getStatusCode());

        $response = $api->request('POST', '/auth/refresh', ['refreshToken' => $refresh], idempotencyKey: 'renew-after-token-revocation');
        self::assertSame(200, $response->getStatusCode());
        $renewed    = UserFlowTest::resource($response, 'session');
        $nextAccess = UserFlowTest::text($renewed, 'accessToken');
        $response   = $api->request('GET', '/identity', token: $nextAccess);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('GET', '/identity', token: $access);
        self::assertSame(401, $response->getStatusCode());

        $response = $api->request('DELETE', '/identity/session', token: $nextAccess);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', ['refreshToken' => $renewed['refreshToken']]);
        self::assertSame(401, $response->getStatusCode());
    }

    /** @return iterable<string, array{Realm, bool}> */
    public static function apis(): iterable
    {
        yield 'UserApi' => [Realm::USER, false];
        yield 'ExampleApi' => [Realm::USER, true];
        yield 'SystemAdminApi' => [Realm::ADMIN, false];
    }
}
