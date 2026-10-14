<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Services\Settings as SettingsValue;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;

final class ExampleApiFixture
{
    /** @return App<ContainerInterface|null> */
    public static function build(CommandBus $commandBus): App
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            Settings::class => new SettingsValue([
                'example-api' => ['api-key' => 'example-api-key'],
                'cdnBaseUrl' => '',
            ]),
            LoggerInterface::class => new NullLogger(),
            CommandBus::class => $commandBus,
        ]);
        AppFactory::setContainer($builder->build());
        $app        = AppFactory::create();
        $directory  = 'src/Backendbase/Infrastructure/Inbound/ExampleApi';
        $middleware = require $directory . '/middleware.php';
        $routes     = require $directory . '/routes.php';
        $middleware($app);
        $routes($app);
        $app->addRoutingMiddleware();

        return $app;
    }
}
