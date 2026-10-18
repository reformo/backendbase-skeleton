<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\Idempotency;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\UserFlowTest;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class AuthenticationReplayTest extends TestCase
{
    #[DataProvider('apis')]
    public function testLoginReplaysTheSameTokenWithoutIssuingAnotherSession(string $apiName, Realm $realm): void
    {
        $fixture = ExampleApiFixture::identity();
        IdentityScenario::admin($fixture);
        $api     = $apiName === 'example' ? IdentityApiFixture::example($fixture) : IdentityApiFixture::create($fixture, $realm);
        $payload = ['identifier' => 'admin@example.com', 'password' => 'valid-password-123'];
        $first   = $api->request('POST', '/auth/login', $payload, idempotencyKey: 'one-login');
        self::assertSame(200, $first->getStatusCode());
        $second   = $api->request('POST', '/auth/login', $payload, idempotencyKey: 'one-login');
        $original = (string) $first->getBody();
        self::assertSame($original, (string) $second->getBody());
        $contentType  = $first->getHeaderLine('Content-Type');
        $cacheControl = $first->getHeaderLine('Cache-Control');
        self::assertSame($contentType, $second->getHeaderLine('Content-Type'));
        self::assertSame($cacheControl, $second->getHeaderLine('Cache-Control'));
        $connection = $fixture->connection();
        $stored     = json_encode($connection->fetchAllAssociative('SELECT * FROM http_request_replay'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('valid-password', $stored);
        self::assertStringNotContainsString('accessToken', $stored);
        self::assertStringNotContainsString('admin@example.com', $stored);
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM http_request_replay'));
        $changed = $api->request('POST', '/auth/login', [...$payload, 'password' => 'different'], idempotencyKey: 'one-login');
        self::assertSame(422, $changed->getStatusCode());
    }

    public function testRevokedCredentialsCannotReadAProtectedReplay(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        $login   = $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123']);
        $session = UserFlowTest::resource($login, 'session');
        $token   = UserFlowTest::text($session, 'accessToken');
        $first   = $api->request('DELETE', '/identity/session', token: $token, idempotencyKey: 'logout');
        $retry   = $api->request('DELETE', '/identity/session', token: $token, idempotencyKey: 'logout');
        $read    = $api->request('GET', '/identity', token: $token);
        self::assertSame(200, $first->getStatusCode());
        self::assertSame(401, $retry->getStatusCode());
        self::assertSame(401, $read->getStatusCode());
    }

    public function testKeysAreScopedToTheInboundApi(): void
    {
        $fixture = ExampleApiFixture::identity();
        IdentityScenario::registered($fixture);
        $user    = IdentityApiFixture::create($fixture, Realm::USER);
        $example = IdentityApiFixture::example($fixture);
        $payload = ['identifier' => 'ada@example.com', 'password' => 'valid-password-123'];
        $first   = $user->request('POST', '/auth/login', $payload, idempotencyKey: 'same-key');
        $second  = $example->request('POST', '/auth/login', $payload, idempotencyKey: 'same-key');
        self::assertSame(200, $second->getStatusCode());
        $original = (string) $first->getBody();
        self::assertNotSame($original, (string) $second->getBody());
    }

    public function testMissingKeyRejectsTheWriteAndLeavesReadsAvailable(): void
    {
        $fixture  = IdentityFixture::create();
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $request  = new ServerRequest(uri: '/auth/login', method: 'POST');
        $response = $api->handle($request);
        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Idempotency-Key', (string) $response->getBody());
        $health    = $api->handle(new ServerRequest(uri: '/_status', method: 'GET'));
        $preflight = $api->handle(new ServerRequest(uri: '/auth/login', method: 'OPTIONS'));
        self::assertSame(200, $health->getStatusCode());
        self::assertSame(204, $preflight->getStatusCode());
        $connection = $fixture->connection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM http_request_replay'));
    }

    /** @return iterable<string, array{string, Realm}> */
    public static function apis(): iterable
    {
        yield 'user' => ['user', Realm::USER];
        yield 'admin' => ['admin', Realm::ADMIN];
        yield 'example' => ['example', Realm::USER];
    }
}
