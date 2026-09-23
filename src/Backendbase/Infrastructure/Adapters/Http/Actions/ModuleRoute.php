<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Actions;

use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;

interface ModuleRoute
{
    public const string ROUTE_KEY = '';

    /** @param RouteCollectorProxy<ContainerInterface> $routeCollector */
    public function __invoke(RouteCollectorProxy $routeCollector): void;

    public function routeKey(): string;
}
