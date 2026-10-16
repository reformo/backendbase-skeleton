<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Inbound\UserApi\Controllers\ModuleRoutes;
use Backendbase\Infrastructure\Inbound\UserApi\Controllers\Root\Preflight;
use Slim\App;

return static function (App $app): void {
    foreach (new ModuleRoutes()->getModules() as $moduleRouteKey => $moduleClass) {
        $app->group('/' . $moduleRouteKey, $moduleClass);
    }

    $collector = $app->getRouteCollector();
    foreach ($collector->getRoutes() as $route) {
        $app->options($route->getPattern(), Preflight::class);
    }
};
