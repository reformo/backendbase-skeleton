<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticateAccount;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Authenticate;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\NotFound;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Root;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use stdClass;

use function json_decode;
use function password_hash;
use function str_repeat;

use const JSON_THROW_ON_ERROR;
use const PASSWORD_ARGON2ID;

final class RootControllersTest extends TestCase
{
    #[Test]
    public function itReturnsApiMetadata(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Root endpoint called');
        $settings = new ApplicationRuntimeSettings(new Settings(['cdnBaseUrl' => 'https://cdn.example.com/']));
        $action   = new Root($settings, $logger);
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/');

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
        $account                         = new AccountAuthentication(
            '7d9f6812-34f8-4bce-9396-82e97c9dd0ce',
            'user@example.com',
            password_hash('secret', PASSWORD_ARGON2ID),
            ['example.add', 'example.change', 'example.remove'],
        );
        $accountAuthenticationRepository = $this->createMock(AccountAuthenticationRepository::class);
        $accountAuthenticationRepository->method('withAuthenticationLock')->willReturnCallback(
            static fn (string $email, callable $authenticate): string => $authenticate(),
        );
        $accountAuthenticationRepository->expects(self::once())
            ->method('findByEmail')
            ->with($account->email())
            ->willReturn($account);
        $tokenIssuer = $this->createMock(TokenIssuer::class);
        $tokenIssuer->expects(self::once())
            ->method('issueNewToken')
            ->with(
                'userId',
                $account->uuid(),
                ['uuid' => $account->uuid(), 'email' => $account->email(), 'privileges' => $account->privileges()],
            )
            ->willReturn('access-token');
        $action  = new Authenticate(
            new AuthenticateAccount($accountAuthenticationRepository, $tokenIssuer),
            $this->createStub(LoggerInterface::class),
        );
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
        $accountAuthenticationRepository = $this->createMock(AccountAuthenticationRepository::class);
        $accountAuthenticationRepository->method('withAuthenticationLock')->willReturnCallback(
            static fn (string $email, callable $authenticate): string => $authenticate(),
        );
        $accountAuthenticationRepository->expects(self::never())->method('findByEmail');
        $tokenIssuer = $this->createMock(TokenIssuer::class);
        $tokenIssuer->expects(self::never())->method('issueNewToken');
        $action   = new Authenticate(
            new AuthenticateAccount($accountAuthenticationRepository, $tokenIssuer),
            $this->createStub(LoggerInterface::class),
        );
        $factory  = new ServerRequestFactory();
        $payloads = [
            null,
            new stdClass(),
            [],
            ['email' => 'not-an-email', 'password' => 'secret'],
            ['email' => 'user@example.com'],
            ['email' => 'user@example.com', 'password' => []],
            ['email' => str_repeat('a', 255) . '@example.com', 'password' => 'secret'],
            ['email' => 'user@example.com', 'password' => str_repeat('a', 1025)],
            ['email' => 'user@example.com', 'password' => 'secret', 'extra' => true],
        ];

        foreach ($payloads as $payload) {
            $request = $factory->createServerRequest('POST', '/authenticate')->withParsedBody($payload);
            $this->assertInvalidAuthenticationInput($action, $request);
        }
    }

    private function assertInvalidAuthenticationInput(Authenticate $action, ServerRequestInterface $request): void
    {
        try {
            $action($request, new Response(), []);
            self::fail('Invalid authentication input must fail.');
        } catch (InvalidUserInput) {
            self::addToAssertionCount(1);
        }
    }
}
