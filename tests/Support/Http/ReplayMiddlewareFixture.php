<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\Adapters\Http\Idempotency\Idempotency;
use Backendbase\Infrastructure\Adapters\Http\Idempotency\ReplayExchange;
use Backendbase\Infrastructure\Adapters\Http\Idempotency\ReplayExecution;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Infrastructure\Adapters\Security\Idempotency\SodiumRequestReplayCipher;
use Backendbase\Shared\Idempotency\RequestReplayStore;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Tests\Support\Time\FrozenClock;

final class ReplayMiddlewareFixture
{
    /**
     * @param callable(ServerRequestInterface, ResponseInterface, array<string, string>): ResponseInterface $handler
     *
     * @return App<null>
     */
    public static function app(callable $handler, RequestReplayStore|null $store = null): App
    {
        $app        = new App(new ResponseFactory());
        $resolver   = $app->getCallableResolver();
        $factory    = $app->getResponseFactory();
        $errors     = new HttpErrorHandler($resolver, $factory, new NullLogger(), new DomainErrorProblemDetailsMapper());
        $cipher     = new SodiumRequestReplayCipher(ReplayFixture::settings());
        $exchange   = new ReplayExchange($cipher, new ReplayExecution($errors));
        $middleware = new Idempotency($store ?? self::store(), $exchange);
        $route      = $app->map(['POST', 'PUT', 'PATCH', 'DELETE', 'GET', 'OPTIONS'], '/write/{identifier}', $handler);
        $route->setName('testWrite');
        $route->add($middleware);
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler($errors);

        return $app;
    }

    public static function request(string $method = 'POST', string $key = 'request-key', string $body = '{}'): ServerRequestInterface
    {
        $stream = new StreamFactory()->createStream($body);

        return new ServerRequest(uri: '/write/one', method: $method, body: $stream, headers: ['Idempotency-Key' => $key, 'Content-Type' => 'application/json']);
    }

    private static function store(): RequestReplayStore
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        ReplayFixture::schema($connection);
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-18T12:00:00Z'));

        return new DoctrineRequestReplayStore($connection, $clock);
    }
}
