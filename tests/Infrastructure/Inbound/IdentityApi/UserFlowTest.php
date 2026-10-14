<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedOwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class UserFlowTest extends TestCase
{
    #[Test]
    public function itCompletesThePublishedHttpFlowAndRejectsTheRevokedToken(): void
    {
        $identity = IdentityFixture::create();
        $api      = IdentityApiFixture::create($identity, Realm::USER);
        $response = $api->request('POST', '/auth/register', ['email' => 'ADA@EXAMPLE.COM', 'loginName' => 'Ada.User', 'password' => 'valid-password-123']);
        self::assertSame(201, $response->getStatusCode());
        $registration = self::data($response);
        self::assertArrayNotHasKey('accessToken', $registration);
        $challenge     = self::text($registration, 'challengeId');
        $container     = $identity->container();
        $notifications = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifications);
        $response = $api->request('POST', '/auth/ownership/verify', ['challengeId' => $challenge, 'code' => $notifications->code($challenge)]);
        self::assertSame(200, $response->getStatusCode());
        $response = $api->request('POST', '/auth/mfa/enrollment', ['loginName' => 'ADA.USER', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        $enrollment = self::data($response);
        $secret     = self::text($enrollment, 'totpSecret');
        self::assertStringStartsWith('otpauth://totp/', self::text($enrollment, 'otpauthUri'));
        $response = $api->request('POST', '/auth/mfa/enrollment/complete', ['challengeId' => self::text($enrollment, 'challengeId'), 'code' => IdentityScenario::code($identity, $secret)]);
        self::assertSame(200, $response->getStatusCode());
        IdentityScenario::advance($identity, 'PT31S');
        $response = $api->request('POST', '/auth/login', ['loginName' => 'ada.user', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        $begin    = self::data($response);
        $response = $api->request('POST', '/auth/login/complete', ['challengeId' => self::text($begin, 'challengeId'), 'code' => IdentityScenario::code($identity, $secret)]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $session  = self::data($response);
        $token    = self::text($session, 'accessToken');
        $response = $api->request('GET', '/identity', token: $token);
        self::assertSame(200, $response->getStatusCode());
        $current = self::data($response);
        self::assertSame($registration['accountId'], $current['accountId']);
        self::assertSame('USER', $current['realm']);
        self::assertSame(200, $api->request('DELETE', '/identity/token', token: $token)->getStatusCode());
        self::assertSame(401, $api->request('GET', '/identity', token: $token)->getStatusCode());
    }

    /** @return array<string, mixed> */
    public static function data(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['data'] ?? null);

        return $payload['data'];
    }

    /** @param array<string, mixed> $data */
    public static function text(array $data, string $name): string
    {
        self::assertIsString($data[$name] ?? null);

        return $data[$name];
    }
}
