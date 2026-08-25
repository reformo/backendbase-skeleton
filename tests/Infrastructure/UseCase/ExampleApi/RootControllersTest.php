<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root\Authenticate;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root\NotFound;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root\Root;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class RootControllersTest extends TestCase
{
    #[Test]
    public function itReturnsApiMetadata(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Root endpoint called');
        $action  = new Root(new Settings(['cdnBaseUrl' => 'https://cdn.example.com/']), $logger);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');

        $response = $action($request, new Response(), []);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://cdn.example.com/', $payload['backendbase-api']['cdnBaseUrl']);
        self::assertArrayNotHasKey('health', $payload);
    }

    #[Test]
    public function itReturnsNotFoundAndAcceptsOptions(): void
    {
        $action  = new NotFound($this->createStub(LoggerInterface::class));
        $factory = new ServerRequestFactory();

        $notFound = $action($factory->createServerRequest('GET', '/missing'), new Response(), []);
        self::assertSame(404, $notFound->getStatusCode());
        $payload = json_decode((string) $notFound->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('http/not-found', $payload['code']);

        $options = $action($factory->createServerRequest('OPTIONS', '/missing'), new Response(), []);
        self::assertSame(204, $options->getStatusCode());
    }

    #[Test]
    public function itIssuesAnAuthenticationToken(): void
    {
        $jwt = $this->createMock(Jwt::class);
        $jwt->expects(self::once())
            ->method('issueNewToken')
            ->with(
                'userId',
                '019ee8a6-903a-75cf-b9a4-e19d6d0db533',
                self::callback(static function (array $user): bool {
                    return $user['email'] === 'user@example.com' && $user['firstName'] === 'Jane';
                }),
            )
            ->willReturn('access-token');
        $action  = new Authenticate($jwt, $this->createStub(LoggerInterface::class));
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/authenticate')
            ->withParsedBody(['email' => 'user@example.com', 'password' => 'secret']);

        $response = $action($request, new Response(), []);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('access-token', $payload['accessToken']);
    }

    #[Test]
    public function itRejectsInvalidAuthenticationInput(): void
    {
        $jwt = $this->createMock(Jwt::class);
        $jwt->expects(self::never())->method('issueNewToken');
        $action   = new Authenticate($jwt, $this->createStub(LoggerInterface::class));
        $factory  = new ServerRequestFactory();
        $payloads = [
            [],
            ['email' => 'not-an-email', 'password' => 'secret'],
            ['email' => 'user@example.com'],
            ['email' => 'user@example.com', 'password' => []],
        ];

        foreach ($payloads as $payload) {
            $request  = $factory->createServerRequest('POST', '/authenticate')->withParsedBody($payload);
            $response = $action($request, new Response(), []);

            self::assertSame(400, $response->getStatusCode());
        }
    }
}
