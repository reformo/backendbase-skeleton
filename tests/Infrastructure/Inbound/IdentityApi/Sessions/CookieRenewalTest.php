<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

use function implode;

final class CookieRenewalTest extends TestCase
{
    public function testCookieTransportHidesSecretsAndReplaysTheCookieAfterALostBody(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::verified($fixture);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $headers  = ['Origin' => 'https://app.example.com'];
        $response = $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123', 'refreshTokenTransport' => 'cookie'], headers: $headers);
        self::assertSame(200, $response->getStatusCode());
        $session = UserFlowTest::resource($response, 'session');
        self::assertArrayNotHasKey('refreshToken', $session);
        $cookie   = SessionHttpScenario::cookie($response);
        $payload  = ['refreshTokenTransport' => 'cookie'];
        $response = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'cookie-renewal', headers: $headers, cookies: $cookie);
        self::assertSame(200, $response->getStatusCode());
        $rotated = SessionHttpScenario::cookie($response);
        self::assertNotSame($cookie, $rotated);
        self::assertArrayNotHasKey('refreshToken', UserFlowTest::resource($response, 'session'));
        $retried = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'cookie-renewal', headers: $headers, cookies: $rotated);
        self::assertSame(200, $retried->getStatusCode());
        self::assertSame((string) $response->getBody(), (string) $retried->getBody());
        self::assertSame($response->getHeader('Set-Cookie'), $retried->getHeader('Set-Cookie'));
        $connection = $fixture->connection();
        $stored     = $connection->fetchFirstColumn('SELECT response FROM http_request_replay');
        self::assertStringNotContainsString($rotated['__Host-backendbase-user-refresh'], implode('', $stored));
        $renewed = UserFlowTest::resource($response, 'session');
        $logout  = $api->request('DELETE', '/identity/session', token: UserFlowTest::text($renewed, 'accessToken'));
        self::assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
        $response = $api->request('POST', '/auth/refresh', $payload, headers: $headers, cookies: $rotated);
        self::assertSame(401, $response->getStatusCode());
    }

    #[DataProvider('untrustedOrigins')]
    public function testCookieLoginRejectsMissingOrUntrustedOriginBeforeReplay(string $origin): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::verified($fixture);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $payload  = ['identifier' => 'ada@example.com', 'password' => 'valid-password-123', 'refreshTokenTransport' => 'cookie'];
        $response = $api->request('POST', '/auth/login', $payload, idempotencyKey: 'browser-login', headers: ['Origin' => 'https://app.example.com']);
        self::assertSame(200, $response->getStatusCode());
        $cookie   = SessionHttpScenario::cookie($response);
        $response = $api->request('POST', '/auth/login', $payload, idempotencyKey: 'browser-login', headers: ['Origin' => $origin]);
        self::assertSame(403, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', ['refreshTokenTransport' => 'cookie'], headers: ['Origin' => $origin], cookies: $cookie);
        self::assertSame(403, $response->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function untrustedOrigins(): iterable
    {
        yield 'missing' => [''];
        yield 'opaque' => ['null'];
        yield 'cross site' => ['https://attacker.example.net'];
        yield 'suffix match' => ['https://app.example.com.attacker.net'];
    }

    public function testCookieModeRejectsBodySecretAndInvalidTransportTypes(): void
    {
        [, $api, $session] = SessionHttpScenario::login();
        $response          = $api->request('POST', '/auth/refresh', ['refreshTokenTransport' => 'cookie', 'refreshToken' => $session['refreshToken']], headers: ['Origin' => 'https://app.example.com']);
        self::assertSame(400, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', ['refreshTokenTransport' => null, 'refreshToken' => $session['refreshToken']]);
        self::assertSame(400, $response->getStatusCode());
        $response = $api->request('POST', '/auth/refresh', ['refreshTokenTransport' => 'body']);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testOldReplayCannotReplaceANewerBrowserCookie(): void
    {
        [, $api]      = SessionHttpScenario::login();
        $headers      = ['Origin' => 'https://app.example.com'];
        $login        = $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123', 'refreshTokenTransport' => 'cookie'], headers: $headers);
        $cookie       = SessionHttpScenario::cookie($login);
        $payload      = ['refreshTokenTransport' => 'cookie'];
        $first        = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'first-rotation', headers: $headers, cookies: $cookie);
        $firstCookie  = SessionHttpScenario::cookie($first);
        $second       = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'second-rotation', headers: $headers, cookies: $firstCookie);
        $secondCookie = SessionHttpScenario::cookie($second);
        $replay       = $api->request('POST', '/auth/refresh', $payload, idempotencyKey: 'first-rotation', headers: $headers, cookies: $secondCookie);
        self::assertSame(409, $replay->getStatusCode());
        self::assertSame([], $replay->getHeader('Set-Cookie'));
        $response = $api->request('POST', '/auth/refresh', $payload, headers: $headers, cookies: $secondCookie);
        self::assertSame(200, $response->getStatusCode());
    }
}
