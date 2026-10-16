<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\UserApi\Controllers\System;

use Backendbase\Infrastructure\Adapters\Http\Actions\ModuleRoute;
use Override;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

final class ModuleConfig implements ModuleRoute
{
    public const string ROUTE_KEY = '_status';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    #[Override]
    public function __invoke(RouteCollectorProxy $routeCollector): void
    {
        $routeCollector->get('', Handlers\Liveness::class)
            ->setName('userLiveness');

        $routeCollector->get('/ready', Handlers\Readiness::class)
            ->setName('userReadiness');
    }

    #[Override]
    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
