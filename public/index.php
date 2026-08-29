<?php

declare(strict_types=1);

$projectRoot = str_replace('/public', '', __DIR__);
chdir($projectRoot);
require 'vendor/autoload.php';

use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Http\Bootstrap\RequestUriNormalizer;
use Backendbase\Shared\Http\Bootstrap\UseCaseTarget;
use Backendbase\Shared\Http\Handlers\ShutdownHandler;
use Backendbase\Shared\Http\ResponseEmitter\ResponseEmitter;
use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Services\Translator;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

$sourceId = $_SERVER['HTTP_X_SOURCE_ID'] ?? null;
$useCase  = is_string($sourceId) ? UseCaseTarget::fromSourceId($sourceId) : null;
if ($useCase === null) {
    header('Content-Type: application/json');
    die(json_encode([
        'type' => 'system/invalid-source-id',
        'title' => 'Invalid source id.',
        'status' => 400,
        'detail' => 'Invalid source id. Source id missing',
    ], JSON_THROW_ON_ERROR));
}

$webroot            = $projectRoot . '/src/Backendbase/Infrastructure/UseCase/' . $useCase->name();
$cacheDirectoryName = basename($useCase->slug());
$cacheDir           = $projectRoot . '/var/cache/' . $cacheDirectoryName;
$_SERVER            = RequestUriNormalizer::normalize($_SERVER);

$configCachePath = $cacheDir . '/merged-conf.php';
$environment     = Environment::PRODUCTION;
if (! file_exists($configCachePath)) {
    if (file_exists($projectRoot . '/.env')) {
        Dotenv::createUnsafeImmutable($projectRoot)->safeLoad();
    }

    $backendbaseEnv = $_ENV['BACKENDBASE_ENV'] ?? getenv('BACKENDBASE_ENV');
    $environment    = is_string($backendbaseEnv) && $backendbaseEnv !== ''
        ? Environment::fromValue($backendbaseEnv)
        : Environment::PRODUCTION;
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if (! file_exists($cacheDir)) {
    if (! mkdir($cacheDir, 0744, true) && ! is_dir($cacheDir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $cacheDir));
    }
}

if ($environment === Environment::PRODUCTION) {
    if (PHP_SAPI === 'cli-server' || ! extension_loaded('apcu')) {
        $containerBuilder->enableCompilation($cacheDir);
    } else {
        $containerBuilder->enableDefinitionCache();
    }

    $containerBuilder->writeProxiesToFile(true, $cacheDir);
}

$configGenerator = new ConfigAggregator(
    [
        new PhpFileProvider('config/autoload/*'),
        new PhpFileProvider('config/' . $useCase->slug() . '/*'),
    ],
    $configCachePath,
);

// Set up settings
$settings = require 'config/settings.php';
$settings($containerBuilder, $configGenerator->getMergedConfig());

// Set up dependencies
$dependencies = require 'config/dependencies.php';
$dependencies($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();


$runtimeSettings = $container->get(ApplicationRuntimeSettings::class);
$routeCacheFile  = $runtimeSettings->routeCacheFile();

// Instantiate the app
AppFactory::setContainer($container);
$app            = AppFactory::create();
$routeCollector = $app->getRouteCollector();
if (! empty($routeCacheFile) && $environment === Environment::PRODUCTION) {
    $routeCollector->setCacheFile($routeCacheFile);
}

$callableResolver = $app->getCallableResolver();


// Register middleware
$middleware = require $webroot . '/middleware.php';
$middleware($app);


// Register routes
$routes = require $webroot . '/routes.php';
$routes($app);
$basePath = $runtimeSettings->basePath();
$app->setBasePath($basePath);
$displayErrorDetails = $runtimeSettings->displaysErrorDetails();
$logError            = $runtimeSettings->logsErrors();
$logErrorDetails     = $runtimeSettings->logsErrorDetails();

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request              = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$responseFactory = $app->getResponseFactory();
$logger          = $container->get(LoggerInterface::class);
$translator      = $container->get(Translator::class);
$errorHandler    = new HttpErrorHandler(
    $callableResolver,
    $responseFactory,
    $logger,
    new DomainErrorProblemDetailsMapper(),
    $translator,
);

// Create Shutdown Handler
$headerSettings  = $container->get(HttpHeaderSettings::class);
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $headerSettings, $displayErrorDetails, $logger);
register_shutdown_function($shutdownHandler);

$app->addBodyParsingMiddleware();

// Add Routing Middleware
$app->addRoutingMiddleware();


// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response        = $app->handle($request);
$responseEmitter = new ResponseEmitter($headerSettings);
$responseEmitter->emit($response);
