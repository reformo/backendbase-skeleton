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
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class IdentityApiFixture
{
    /** @param App<ContainerInterface> $app */
    public function __construct(private App $app)
    {
    }

    public static function create(IdentityFixture $identity, Realm $realm): self
    {
        $container = $identity->container();
        $app       = AppFactory::create(container: $container);
        $name      = $realm === Realm::USER ? 'UserApi' : 'SystemAdminApi';
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

        return new self($app);
    }

    /** @param array<string, mixed>|null $payload */
    public function request(string $method, string $path, array|null $payload = null, string|null $token = null): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, $path);
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

        return $this->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $app = $this->app;

        return $app->handle($request);
    }
}
