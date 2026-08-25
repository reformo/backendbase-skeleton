<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ModuleConfig;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\ModuleRoutes;
use Backendbase\Shared\Services\Settings as SettingsValue;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Factory\AppFactory;

use function dirname;

final class ModuleRoutingTest extends TestCase
{
    #[Test]
    public function itRegistersAllExampleModuleRoutes(): void
    {
        $app    = AppFactory::create();
        $module = new ModuleConfig();
        $app->group('/example-types', $module);

        self::assertSame('example-types', $module->routeKey());
        self::assertCount(6, $app->getRouteCollector()->getRoutes());
        self::assertSame(
            'addNewExample',
            $app->getRouteCollector()->getNamedRoute('addNewExample')->getName(),
        );
        self::assertSame(
            'removeExample',
            $app->getRouteCollector()->getNamedRoute('removeExample')->getName(),
        );

        $modules = (new ModuleRoutes())->getModules();
        self::assertSame(ModuleConfig::class, $modules['example-types']);
    }

    #[Test]
    public function itRegistersTheCompleteApiRouteFile(): void
    {
        $app    = AppFactory::create();
        $routes = require dirname(__DIR__, 4) . '/src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php';
        $routes($app);

        self::assertCount(11, $app->getRouteCollector()->getRoutes());
    }

    #[Test]
    public function itProtectsMetadataAndKeepsLivenessPublic(): void
    {
        AppFactory::setContainer($this->container());
        $app        = AppFactory::create();
        $middleware = require dirname(__DIR__, 4)
            . '/src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php';
        $routes     = require dirname(__DIR__, 4)
            . '/src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php';
        $middleware($app);
        $routes($app);
        $app->addRoutingMiddleware();

        $requestFactory = new ServerRequestFactory();
        $unprotected    = $app->handle($requestFactory->createServerRequest('GET', '/'));
        $protected      = $app->handle(
            $requestFactory->createServerRequest('GET', '/')->withHeader('Backendbase-Api-Key', 'example-api-key'),
        );
        $liveness       = $app->handle($requestFactory->createServerRequest('GET', '/_status'));

        self::assertSame(400, $unprotected->getStatusCode());
        self::assertSame(200, $protected->getStatusCode());
        self::assertSame(200, $liveness->getStatusCode());
    }

    private function container(): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            Settings::class => new SettingsValue([
                'example-api' => ['api-key' => 'example-api-key'],
                'cdnBaseUrl' => '',
            ]),
            LoggerInterface::class => new NullLogger(),
        ]);

        return $containerBuilder->build();
    }
}
