<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Http;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Domain\IdentityAndAccess\Adapters\Http\AuthorizationMiddleware;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
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
        $jwt = $this->createMock(Jwt::class);
        $jwt->expects(self::never())->method('validateToken');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = (new AuthorizationMiddleware($jwt))->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            $handler,
        );

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('identity-and-access/authorization-header-not-found', $payload['code']);
    }

    #[Test]
    public function itReturnsAValidationFailureAsBadRequest(): void
    {
        $jwt = $this->createStub(Jwt::class);
        $jwt->method('validateToken')->willThrowException(AuthorizationExpired::create('Token expired.'));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = (new AuthorizationMiddleware($jwt))->process(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/resource')
                ->withHeader('Authorization', 'Bearer invalid-token'),
            $handler,
        );

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Token expired.', $payload['detail']);
    }

    #[Test]
    public function itAddsValidatedIdentityDataToTheRequest(): void
    {
        $registeredAt = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $jwt          = $this->createStub(Jwt::class);
        $jwt->method('validateToken')->willReturn([
            'user' => ['uuid' => 'user-id', 'registeredAt' => $registeredAt],
            'privileges' => ['read-example'],
        ]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (ServerRequestInterface $request): bool {
                $acl      = $request->getAttribute(Acl::class);
                $timezone = $request->getAttribute('clientTimezone');

                self::assertSame('user-id', $request->getAttribute('authorizedUserId'));
                self::assertSame(
                    '2026-08-25T10:00:00+00:00',
                    $request->getAttribute('authorizedUserData')['registeredAt'],
                );
                self::assertInstanceOf(DateTimeZone::class, $timezone);
                self::assertSame('UTC', $timezone->getName());
                self::assertInstanceOf(Acl::class, $acl);
                self::assertTrue($acl->isAllowed('read-example'));

                return true;
            }))
            ->willReturn(new EmptyResponse(204));

        $response = (new AuthorizationMiddleware($jwt))->process(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/resource')
                ->withHeader('Authorization', 'Bearer valid-token'),
            $handler,
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function itRejectsTokenDataWithoutAUser(): void
    {
        $jwt = $this->createStub(Jwt::class);
        $jwt->method('validateToken')->willReturn(['privileges' => []]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $this->expectException(AuthorizationExpired::class);

        (new AuthorizationMiddleware($jwt))->process(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/resource')
                ->withHeader('Authorization', 'Bearer valid-token'),
            $handler,
        );
    }
}
