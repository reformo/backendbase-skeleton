<?php

declare(strict_types=1);

namespace Tests;

use DI\ContainerBuilder;
use Exception;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Laminas\Diactoros\ServerRequest as SlimRequest;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory;

use function dirname;
use function fopen;

class ExampleApiTestCase extends PHPUnit_TestCase
{
    /**
     * @return App<ContainerInterface|null>
     *
     * @throws Exception
     */
    protected function getAppInstance(): App
    {
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        // Container intentionally isn't compiled for tests.
        $configGenerator = new ConfigAggregator(
            [new PhpFileProvider('config/autoload/*'), new PhpFileProvider('config/example-api/*')],
        );

        $settings = require 'config/settings.php';
        $settings($containerBuilder, $configGenerator->getMergedConfig());

        // Set up dependencies
        $dependencies = require 'config/dependencies.php';
        $dependencies($containerBuilder);

        // Build PHP-DI Container instance
        $container = $containerBuilder->build();

        // Instantiate the app
        AppFactory::setContainer($container);

        $app = AppFactory::create();
        // Register middleware
        $middleware = require dirname(__DIR__) . '/src/Infrastructure/UseCase/ExampleApi/webroot/middleware.php';
        $middleware($app);

// Register routes
        $routes = require dirname(__DIR__) . '/src/Infrastructure/UseCase/ExampleApi/webroot/routes.php';
        $routes($app);

        return $app;
    }

    /**
     * @param array<non-empty-string, array<string>|string> $headers
     * @param array<string, mixed>                          $cookies
     * @param array<string, mixed>                          $serverParams
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = [],
    ): Request {
        $uri = new Uri('');
        $uri->withPath($path)
            ->withPort(80);

        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new RuntimeException('The request body stream could not be opened.');
        }

        return new SlimRequest(
            serverParams: $serverParams,
            uri: $uri,
            method: $method,
            body: $handle,
            headers: $headers,
            cookieParams: $cookies,
        );
    }
}
