<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

final class RenewalFlowTest extends TestCase
{
    #[DataProvider('apis')]
    public function testRenewalWorksAfterAccessExpiryWithTheSelectedRealmDurations(Realm $realm, bool $example, int $tokenSeconds, int $idleSeconds, int $maximumSeconds): void
    {
        [$fixture, $api, $session] = SessionHttpScenario::login($realm, $example);
        $access                    = UserFlowTest::text($session, 'accessToken');
        $refresh                   = UserFlowTest::text($session, 'refreshToken');
        $expires                   = new DateTimeImmutable(UserFlowTest::text($session, 'expiresAt'));
        $idle                      = new DateTimeImmutable(UserFlowTest::text($session, 'sessionIdleExpiresAt'));
        $maximum                   = new DateTimeImmutable(UserFlowTest::text($session, 'sessionExpiresAt'));
        self::assertSame($idleSeconds - $tokenSeconds, $idle->getTimestamp() - $expires->getTimestamp());
        self::assertSame($maximumSeconds - $tokenSeconds, $maximum->getTimestamp() - $expires->getTimestamp());
        IdentityScenario::advance($fixture, 'PT' . $tokenSeconds . 'S');
        $response = $api->request('GET', '/identity', token: $access);
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', ['refreshToken' => $refresh], idempotencyKey: 'renew-on-expiry');
        self::assertSame(200, $response->getStatusCode());
        $renewed = UserFlowTest::resource($response, 'session');
        self::assertNotSame($refresh, $renewed['refreshToken']);
        self::assertSame($session['sessionExpiresAt'], $renewed['sessionExpiresAt']);
        $response = $api->request('GET', '/identity', token: UserFlowTest::text($renewed, 'accessToken'));
        self::assertSame(200, $response->getStatusCode());
    }

    /** @return iterable<string, array{Realm, bool, int, int, int}> */
    public static function apis(): iterable
    {
        yield 'UserApi' => [Realm::USER, false, 900, 86400, 604800];
        yield 'ExampleApi' => [Realm::USER, true, 900, 86400, 604800];
        yield 'SystemAdminApi' => [Realm::ADMIN, false, 300, 1800, 28800];
    }

    public function testRetryReplaysTheSameRotationButANewOperationWithTheConsumedTokenRevokesTheSession(): void
    {
        [, $api, $session] = SessionHttpScenario::login();
        $payload           = ['refreshToken' => $session['refreshToken']];
        $response          = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'one-renewal');
        self::assertSame(200, $response->getStatusCode());
        $renewed = UserFlowTest::resource($response, 'session');
        $retried = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'one-renewal');
        self::assertSame((string) $response->getBody(), (string) $retried->getBody());
        $response = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'different-renewal');
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'one-renewal');
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request('GET', '/identity', token: UserFlowTest::text($renewed, 'accessToken'));
        self::assertSame(401, $response->getStatusCode());
    }

    #[DataProvider('sessionEnds')]
    public function testEndedSessionsCannotRenewOrReplay(string $ending): void
    {
        [$fixture, $api, $session] = SessionHttpScenario::login();
        $payload                   = ['refreshToken' => $session['refreshToken']];
        $response                  = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'completed-renewal');
        $renewed                   = UserFlowTest::resource($response, 'session');
        if ($ending === 'idle') {
            IdentityScenario::advance($fixture, 'PT86400S');
        }

        if ($ending !== 'idle') {
            $response = $api->request('DELETE', '/identity/' . $ending, token: UserFlowTest::text($renewed, 'accessToken'));
            self::assertSame(200, $response->getStatusCode());
        }

        $response = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'completed-renewal');
        self::assertSame(401, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', ['refreshToken' => $renewed['refreshToken']]);
        self::assertSame(401, $response->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function sessionEnds(): iterable
    {
        yield 'logout' => ['session'];
        yield 'closure' => ['account'];
        yield 'idle expiry' => ['idle'];
    }
}
