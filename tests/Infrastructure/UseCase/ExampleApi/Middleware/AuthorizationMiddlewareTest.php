<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi\Middleware;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Infrastructure\UseCase\ExampleApi\Middleware\AuthorizationMiddleware;
use Backendbase\Shared\Authorization\AccessControl;
use DateTimeImmutable;
use DateTimeZone;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class AuthorizationMiddlewareTest extends TestCase
{
    #[Test]
    public function itRejectsAMalformedAuthorizationHeader(): void
    {
        $tokenValidator = $this->createMock(TokenValidator::class);
        $tokenValidator->expects(self::never())->method('validateToken');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = (new AuthorizationMiddleware($tokenValidator))->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            $handler,
        );

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('identity-access/authorization-expired', $payload['code']);
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function itReturnsAStableUnauthorizedResponseForValidationFailures(): void
    {
        $tokenValidator = $this->createStub(TokenValidator::class);
        $tokenValidator->method('validateToken')->willThrowException(AuthorizationExpired::create('Token expired.'));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = (new AuthorizationMiddleware($tokenValidator))->process(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/resource')
                ->withHeader('Authorization', 'Bearer invalid-token'),
            $handler,
        );

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('The bearer token is missing or invalid.', $payload['detail']);
    }

    #[Test]
    public function itAddsValidatedIdentityDataToTheRequest(): void
    {
        $registeredAt   = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $tokenValidator = $this->createStub(TokenValidator::class);
        $tokenValidator->method('validateToken')->willReturn([
            'user' => [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'registeredAt' => $registeredAt,
            ],
            'privileges' => ['read-example'],
        ]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (ServerRequestInterface $request): bool {
                $acl           = $request->getAttribute(Acl::class);
                $accessControl = $request->getAttribute(AccessControl::class);
                $timezone      = $request->getAttribute('clientTimezone');

                self::assertSame(
                    '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                    $request->getAttribute('authorizedUserId'),
                );
                self::assertSame(
                    '2026-08-25T10:00:00+00:00',
                    $request->getAttribute('authorizedUserData')['registeredAt'],
                );
                self::assertInstanceOf(DateTimeZone::class, $timezone);
                self::assertSame('UTC', $timezone->getName());
                self::assertInstanceOf(Acl::class, $acl);
                self::assertSame($acl, $accessControl);
                self::assertTrue($acl->isAllowed('read-example'));

                return true;
            }))
            ->willReturn(new EmptyResponse(204));

        $response = (new AuthorizationMiddleware($tokenValidator))->process(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/resource')
                ->withHeader('Authorization', 'Bearer valid-token')
                ->withHeader('The-Timezone-IANA', 'UTC'),
            $handler,
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function itRejectsTokenDataWithoutAUser(): void
    {
        $tokenValidator = $this->createStub(TokenValidator::class);
        $tokenValidator->method('validateToken')->willReturn(['privileges' => []]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = (new AuthorizationMiddleware($tokenValidator))->process(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/resource')
                ->withHeader('Authorization', 'Bearer valid-token'),
            $handler,
        );

        self::assertSame(401, $response->getStatusCode());
    }
}
