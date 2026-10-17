<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Authentication\ModuleConfig as AuthenticationModuleConfig;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ModuleConfig;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Identity\ModuleConfig as IdentityModuleConfig;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\ModuleRoutes;
use Backendbase\Shared\Services\Settings as SettingsValue;
use Backendbase\Shared\Settings;
use cebe\openapi\spec\Operation;
use DI\ContainerBuilder;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorInterface;
use Tests\Support\Http\ApiResponseAssertions;

use function strtoupper;

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
            '/example-types/{type-slug}/groups',
            $app->getRouteCollector()->getNamedRoute('getExampleGroupsByType')->getPattern(),
        );
        self::assertSame(
            '/example-types/{type-slug}/groups/{example-group}/examples',
            $app->getRouteCollector()->getNamedRoute('getExamplesByGroup')->getPattern(),
        );
        self::assertSame(
            '/example-types/{type-slug}/groups/{example-group}/examples/{example-key}',
            $app->getRouteCollector()->getNamedRoute('getExampleByCriteria')->getPattern(),
        );
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
        self::assertSame(AuthenticationModuleConfig::class, $modules['auth']);
        self::assertSame(IdentityModuleConfig::class, $modules['identity']);
        self::assertSame('auth', new AuthenticationModuleConfig()->routeKey());
        self::assertSame('identity', new IdentityModuleConfig()->routeKey());
    }

    #[Test]
    public function itRegistersTheCompleteApiRouteFile(): void
    {
        $app    = AppFactory::create();
        $routes = require 'src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php';
        $routes($app);

        self::assertCount(19, $app->getRouteCollector()->getRoutes());
    }

    #[Test]
    public function itMatchesModuleHandlersAndRouteNamesToTheDocumentedOperations(): void
    {
        $app      = AppFactory::create();
        $register = require 'src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php';
        $register($app);
        $collector = $app->getRouteCollector();
        $document  = ApiResponseAssertions::document('resources/api-docs/example-api/example-openapi.yml');
        $paths     = $document->paths;
        foreach ($paths->getPaths() as $path => $pathItem) {
            $this->assertModuleOperations($path, $pathItem->getOperations(), $collector);
        }
    }

    /** @param array<string, Operation> $operations */
    private function assertModuleOperations(string $path, array $operations, RouteCollectorInterface $collector): void
    {
        foreach ($operations as $method => $operation) {
            $tag = $operation->tags[0];
            if ($tag === 'System') {
                continue;
            }

            $route = $collector->getNamedRoute($operation->operationId);
            self::assertSame($path, $route->getPattern());
            self::assertSame([strtoupper($method)], $route->getMethods());
            $controller = $route->getCallable();
            self::assertIsString($controller);
            self::assertStringStartsWith('Backendbase\\Infrastructure\\Inbound\\ExampleApi\\Controllers\\' . $tag . '\\Handlers\\', $controller);
        }
    }

    #[Test]
    public function itProtectsMetadataAndKeepsLivenessPublic(): void
    {
        AppFactory::setContainer($this->container());
        $app        = AppFactory::create();
        $middleware = require 'src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php';
        $routes     = require 'src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php';
        $middleware($app);
        $routes($app);
        $app->addRoutingMiddleware();

        $requestFactory = new ServerRequestFactory();
        $unprotected    = $app->handle($requestFactory->createServerRequest('GET', '/'));
        $protected      = $app->handle(
            $requestFactory->createServerRequest('GET', '/')->withHeader('Backendbase-Api-Key', 'example-api-key'),
        );
        $liveness       = $app->handle($requestFactory->createServerRequest('GET', '/_status'));

        self::assertSame(401, $unprotected->getStatusCode());
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
