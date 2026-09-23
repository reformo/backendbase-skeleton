<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Greeting;

use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Greeting\Handlers\QueueGreetingRequest;
use Backendbase\Infrastructure\UseCase\ExampleApi\Middleware\AuthorizationMiddleware;
use Override;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

final class ModuleConfig implements ModuleRoute
{
    public const string ROUTE_KEY = 'examples';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->post('/hello', QueueGreetingRequest::class)
            ->setName('queueGreeting')
            ->add(AuthorizationMiddleware::class);
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
