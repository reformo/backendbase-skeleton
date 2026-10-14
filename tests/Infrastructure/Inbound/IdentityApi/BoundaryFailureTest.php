<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final class BoundaryFailureTest extends TestCase
{
    /** @param array<string, mixed>|null $payload */
    #[DataProvider('invalidRequests')]
    public function testInvalidPublicRequestsFailBeforeStateChanges(string $method, string $path, array|null $payload, int $status): void
    {
        $fixture  = IdentityFixture::create();
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $response = $api->request($method, $path, $payload);
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $problem = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertArrayHasKey('status', $problem);
        self::assertSame($status, $problem['status']);
        $connection = $fixture->connection();
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
        self::assertStringNotContainsString('valid-password-123', (string) $response->getBody());
    }

    /** @return iterable<string, array{string, string, array<string, mixed>|null, int}> */
    public static function invalidRequests(): iterable
    {
        $valid = ['email' => 'test@example.com', 'loginName' => 'test.user', 'password' => 'valid-password-123'];

        yield 'unknown field' => ['POST', '/auth/register', [...$valid, 'admin' => true], 400];
        yield 'missing field' => ['POST', '/auth/register', ['email' => 'test@example.com'], 400];
        yield 'wrong type' => ['POST', '/auth/register', [...$valid, 'password' => 123456789012], 400];
        yield 'oversized body' => ['POST', '/auth/register', [...$valid, 'password' => str_repeat('a', 70000)], 400];
        yield 'missing content type' => ['POST', '/auth/register', null, 400];
        yield 'unknown login' => ['POST', '/auth/login', ['loginName' => 'unknown', 'password' => 'valid-password-123'], 401];
        yield 'unknown enrollment' => ['POST', '/auth/mfa/enrollment', ['loginName' => 'unknown', 'password' => 'valid-password-123'], 401];
        yield 'unknown resend' => ['POST', '/auth/ownership/resend', ['loginName' => 'unknown', 'password' => 'valid-password-123'], 401];
        yield 'unknown proof' => ['POST', '/auth/ownership/verify', ['challengeId' => IdentityScenario::id(), 'code' => '123456'], 401];
        yield 'malformed proof' => ['POST', '/auth/ownership/verify', ['challengeId' => IdentityScenario::id(), 'code' => '123'], 400];
        yield 'missing bearer' => ['GET', '/identity', null, 401];
        yield 'invalid bearer' => ['GET', '/identity', null, 401];
        yield 'removed greeting' => ['POST', '/greetings', [], 404];
    }

    #[DataProvider('missingRoutes')]
    public function testMissingRoutesReturnMatchingHttpAndProblemStatus(Realm $realm, string $method): void
    {
        $api      = IdentityApiFixture::create(IdentityFixture::create(), $realm);
        $response = $api->request($method, '/missing-route');
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $problem = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame(404, $problem['status']);
        self::assertSame('http/resource-not-found', $problem['code']);
        self::assertSame('Not Found', $problem['title']);
    }

    /** @return iterable<string, array{Realm, string}> */
    public static function missingRoutes(): iterable
    {
        yield 'user get' => [Realm::USER, 'GET'];
        yield 'user post' => [Realm::USER, 'POST'];
        yield 'admin get' => [Realm::ADMIN, 'GET'];
        yield 'admin post' => [Realm::ADMIN, 'POST'];
    }

    public function testHealthRoutesAreAnonymousAndAdminRegistrationIsAbsent(): void
    {
        $api = IdentityApiFixture::create(IdentityFixture::create(), Realm::ADMIN);
        self::assertSame(200, $api->request('GET', '/_status')->getStatusCode());
        self::assertSame(200, $api->request('GET', '/_status/ready')->getStatusCode());
        self::assertSame(404, $api->request('POST', '/auth/register', [])->getStatusCode());
        self::assertSame(401, $api->request('POST', '/administration/admins/invite', [])->getStatusCode());
    }

    public function testMalformedJsonAndUnsupportedBodyTypesAreRejected(): void
    {
        $api     = IdentityApiFixture::create(IdentityFixture::create(), Realm::USER);
        $request = new ServerRequest(uri: '/auth/register', method: 'POST');
        self::assertSame(400, $api->handle($request->withHeader('Content-Type', 'text/plain'))->getStatusCode());
        self::assertSame(400, $api->handle($request->withHeader('Content-Type', 'application/json'))->getStatusCode());
    }

    public function testOwnershipResendReturnsANewChallengeThroughTheRegisteredHandler(): void
    {
        $fixture = IdentityFixture::create();
        $api     = IdentityApiFixture::create($fixture, Realm::USER);
        self::assertSame(201, $api->request('POST', '/auth/register', ['email' => 'test@example.com', 'loginName' => 'test.user', 'password' => 'valid-password-123'])->getStatusCode());
        $resend = $api->request('POST', '/auth/ownership/resend', ['loginName' => 'test.user', 'password' => 'valid-password-123']);
        self::assertSame(200, $resend->getStatusCode());
        $data = UserFlowTest::resource($resend, 'challenge');
        self::assertSame(300, $data['expiresIn']);
        self::assertNotEmpty($data['challengeId']);
    }
}
