<?php

declare(strict_types=1);

namespace Tests;

use DI\ContainerBuilder;
use Exception;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

class TestCase extends PHPUnit_TestCase
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

        // Container intentionally not compiled for tests.
        $configGenerator = new ConfigAggregator(
            [
                new PhpFileProvider('config/autoload/*'),
                new PhpFileProvider('config/example-api/*'),
            ],
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

        return AppFactory::create();
    }
}
