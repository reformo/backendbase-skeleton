<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedOwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_keys;
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
        $registration = self::resource($response, 'registration');
        self::assertArrayNotHasKey('accessToken', $registration);
        $challenge     = self::text($registration, 'challengeId');
        $container     = $identity->container();
        $notifications = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifications);
        $response = $api->request('POST', '/auth/ownership/verify', ['challengeId' => $challenge, 'code' => $notifications->code($challenge)]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], self::resource($response, 'ownershipVerification'));
        $response = $api->request('POST', '/auth/login', ['loginName' => 'ada.user', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $session  = self::resource($response, 'session');
        $token    = self::text($session, 'accessToken');
        $response = $api->request('GET', '/identity', token: $token);
        self::assertSame(200, $response->getStatusCode());
        $current = self::resource($response, 'user');
        self::assertSame($registration['accountId'], $current['accountId']);
        self::assertSame('USER', $current['realm']);
        $response = $api->request('DELETE', '/identity/token', token: $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], self::resource($response, 'token'));
        self::assertSame(401, $api->request('GET', '/identity', token: $token)->getStatusCode());
    }

    /** @return array<string, mixed> */
    public static function resource(ResponseInterface $response, string $responseKey): array
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame([$responseKey], array_keys($payload));
        self::assertIsArray($payload[$responseKey] ?? null);

        return $payload[$responseKey];
    }

    /** @param array<string, mixed> $data */
    public static function text(array $data, string $name): string
    {
        self::assertIsString($data[$name] ?? null);

        return $data[$name];
    }
}
