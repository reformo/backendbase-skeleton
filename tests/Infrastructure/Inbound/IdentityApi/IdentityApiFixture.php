<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\Adapters\Http\Middleware\LimitJsonBody;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteInterface;
use Tests\Support\Http\ApiResponseAssertions;

use function in_array;
use function json_encode;
use function preg_match;
use function preg_replace;

use const JSON_THROW_ON_ERROR;

final readonly class IdentityApiFixture
{
    /** @param App<ContainerInterface> $app */
    public function __construct(private App $app, private string $slug)
    {
    }

    public static function create(IdentityFixture $identity, Realm $realm): self
    {
        return self::build($identity, $realm === Realm::USER ? 'UserApi' : 'SystemAdminApi', $realm === Realm::USER ? 'user-api' : 'system-admin-api');
    }

    public static function example(IdentityFixture $identity): self
    {
        return self::build($identity, 'ExampleApi', 'example-api');
    }

    private static function build(IdentityFixture $identity, string $name, string $slug): self
    {
        $container = $identity->container();
        $app       = AppFactory::create(container: $container);
        $root      = 'src/Backendbase/Infrastructure/Inbound/' . $name;
        $routes    = require $root . '/routes.php';
        $routes($app);
        $middleware = require $root . '/middleware.php';
        $middleware($app);
        $app->addBodyParsingMiddleware();
        $app->add(LimitJsonBody::class);
        $app->addRoutingMiddleware();
        $middleware = $app->addErrorMiddleware(false, false, false);
        $middleware->setDefaultErrorHandler(new HttpErrorHandler($app->getCallableResolver(), $app->getResponseFactory(), new NullLogger(), new DomainErrorProblemDetailsMapper()));

        return new self($app, $slug);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string>     $headers
     * @param array<string, string>     $cookies
     */
    public function request(string $method, string $path, array|null $payload = null, string|null $token = null, string|null $idempotencyKey = null, array $headers = [], array $cookies = []): ResponseInterface
    {
        $request    = new ServerRequestFactory()->createServerRequest($method, $path, ['REMOTE_ADDR' => '127.0.0.1']);
        $identifier = Uuid::uuid7();
        $key        = $idempotencyKey ?? $identifier->toString();
        $request    = $request->withHeader('Idempotency-Key', $key);
        if ($this->slug === 'example-api') {
            $request = $request->withHeader('Backendbase-Api-Key', 'example-api-key');
        }

        if ($payload !== null) {
            $stream = new Stream('php://temp', 'r+');
            $stream->write(json_encode($payload, JSON_THROW_ON_ERROR));
            $stream->rewind();
            $request = $request->withBody($stream);
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->handle($request->withCookieParams($cookies));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $app = $this->app;

        $response  = $app->handle($request);
        $collector = $app->getRouteCollector();
        $routes    = $collector->getRoutes();
        $this->assertResponse($request, $response, $routes);

        return $response;
    }

    /** @param array<RouteInterface> $routes */
    private function assertResponse(ServerRequestInterface $request, ResponseInterface $response, array $routes): void
    {
        $uri    = $request->getUri();
        $path   = $uri->getPath();
        $method = $request->getMethod();
        if ($method === 'OPTIONS') {
            return;
        }

        foreach ($routes as $route) {
            $this->assertRouteResponse($route, $method, $path, $response);
        }
    }

    private function assertRouteResponse(RouteInterface $route, string $method, string $path, ResponseInterface $response): void
    {
        $pattern = $route->getPattern();
        if ($pattern === '/{routes:.*}') {
            return;
        }

        $expression = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        Assert::assertIsString($expression);
        $methods = $route->getMethods();
        if (! in_array($method, $methods, true) || preg_match('~^' . $expression . '$~D', $path) !== 1) {
            return;
        }

        ApiResponseAssertions::assertMatches($this->slug, $method, $pattern, $response);
    }
}
