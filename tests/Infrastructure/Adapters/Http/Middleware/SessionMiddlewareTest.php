<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Middleware;

use Backendbase\Infrastructure\Adapters\Http\Middleware\SessionMiddleware;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function session_destroy;
use function session_id;
use function session_start;
use function session_status;
use function session_write_close;
use function uniqid;

use const PHP_SESSION_ACTIVE;

final class SessionMiddlewareTest extends TestCase
{
    #[Test]
    public function itAddsSessionDataForAuthorizedRequests(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_id('backendbase-' . uniqid());
        session_start();
        $_SESSION = ['userId' => 'user-id'];
        session_write_close();
        $originalAuthorization         = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';
        $handler                       = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (ServerRequestInterface $request): bool {
                self::assertSame(['userId' => 'user-id'], $request->getAttribute('session'));

                return true;
            }))
            ->willReturn(new EmptyResponse(204));

        try {
            $response = new SessionMiddleware()->process(
                (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
                $handler,
            );
            self::assertSame(204, $response->getStatusCode());
        } finally {
            if ($originalAuthorization === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalAuthorization;
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            session_destroy();
            $_SESSION = [];
        }
    }
}
