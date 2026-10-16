<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_keys;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class UserFlowTest extends TestCase
{
    #[DataProvider('revocations')]
    public function testPublishedHttpFlowRejectsTheTokenAfterRevocation(string $resource): void
    {
        $identity = IdentityFixture::create();
        $api      = IdentityApiFixture::create($identity, Realm::USER);
        $response = $api->request('POST', '/auth/register', ContactScenario::verified($identity, 'ADA@EXAMPLE.COM'));
        self::assertSame(201, $response->getStatusCode());
        $registration = self::resource($response, 'registration');
        self::assertArrayNotHasKey('accessToken', $registration);
        $response = $api->request('POST', '/auth/login', ['identifier' => 'ada@example.com', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $session  = self::resource($response, 'session');
        $token    = self::text($session, 'accessToken');
        $response = $api->request('GET', '/identity', token: $token);
        self::assertSame(200, $response->getStatusCode());
        $current = self::resource($response, 'user');
        self::assertSame($registration['accountId'], $current['accountId']);
        self::assertSame('USER', $current['realm']);
        $response = $api->request('DELETE', '/identity/' . $resource, token: $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['completed' => true], self::resource($response, $resource));
        self::assertSame(401, $api->request('GET', '/identity', token: $token)->getStatusCode());
    }

    /** @return array<string, array{string}> */
    public static function revocations(): array
    {
        return ['token revocation' => ['token'], 'session logout' => ['session']];
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
