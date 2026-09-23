<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Middleware;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Infrastructure\Inbound\ExampleApi\Middleware\AuthorizationMiddleware;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthorizationMiddlewareTimezoneTest extends TestCase
{
    #[Test]
    public function itRejectsAnInvalidTimezone(): void
    {
        $tokenValidator = $this->createStub(TokenValidator::class);
        $tokenValidator->method('validateToken')->willReturn([
            'user' => ['uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586'],
            'privileges' => [],
        ]);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/resource')
            ->withHeader('Authorization', 'Bearer valid-token')
            ->withHeader('The-Timezone-IANA', 'invalid/timezone');

        $response = (new AuthorizationMiddleware($tokenValidator))->process($request, $handler);

        self::assertSame(400, $response->getStatusCode());
    }
}
