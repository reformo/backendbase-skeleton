<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\ModuleRoutes;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Authenticate;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Liveness;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\NotFound;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Readiness;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Root;
use Slim\App;

return static function (App $app): void {
    $app->get('/', Root::class);
    $app->get('/_status', Liveness::class);
    $app->get('/_status/ready', Readiness::class);
    $app->post('/auth', Authenticate::class);

    foreach (new ModuleRoutes()->getModules() as $moduleRouteKey => $invokableFQCN) {
        $app->group('/' . $moduleRouteKey, $invokableFQCN);
    }

    $app->any('/{routes:.*}', NotFound::class);
};
