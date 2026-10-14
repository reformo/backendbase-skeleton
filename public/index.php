<?php

declare(strict_types=1);

$projectRoot = str_replace('/public', '', __DIR__);
chdir($projectRoot);
require 'vendor/autoload.php';

use Backendbase\Infrastructure\Adapters\Http\Bootstrap\ConsumerApiTarget;
use Backendbase\Infrastructure\Adapters\Http\Bootstrap\RequestUriNormalizer;
use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\Handlers\ShutdownHandler;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestMetadata;
use Backendbase\Infrastructure\Adapters\Http\Middleware\LimitJsonBody;
use Backendbase\Infrastructure\Adapters\Http\Middleware\MeasureRequest;
use Backendbase\Infrastructure\Adapters\Http\ResponseEmitter\ResponseEmitter;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Services\Translator;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

$consumerApi = ConsumerApiTarget::fromRequest($_SERVER);
if ($consumerApi === null) {
    http_response_code(400);
    header('Content-Type: application/problem+json');
    die(json_encode([
        'type' => 'system/invalid-source-id',
        'code' => 'system/invalid-source-id',
        'title' => 'Invalid source id.',
        'status' => 400,
        'detail' => 'Invalid source id. Source id missing',
    ], JSON_THROW_ON_ERROR));
}

$webroot = $projectRoot . '/src/Backendbase/Infrastructure/Inbound/' . $consumerApi->name();
try {
    RequestMetadata::validate($_SERVER);
} catch (InvalidUserInput $exception) {
    http_response_code(400);
    header('Content-Type: application/problem+json');
    die(json_encode([
        'type' => 'system/invalid-request-metadata',
        'code' => 'system/invalid-request-metadata',
        'title' => 'Invalid request metadata.',
        'status' => 400,
        'detail' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR));
}

$cacheDirectoryName = basename($consumerApi->slug());
$cacheDir           = $projectRoot . '/var/cache/' . $cacheDirectoryName;
$_SERVER            = RequestUriNormalizer::normalize($_SERVER);

if (file_exists($projectRoot . '/.env')) {
    Dotenv::createUnsafeImmutable($projectRoot)->safeLoad();
}

$backendbaseEnv = $_ENV['BACKENDBASE_ENV'] ?? getenv('BACKENDBASE_ENV');
$environment    = is_string($backendbaseEnv) && $backendbaseEnv !== ''
    ? Environment::fromValue($backendbaseEnv)
    : Environment::PRODUCTION;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if (! file_exists($cacheDir)) {
    if (! mkdir($cacheDir, 0744, true) && ! is_dir($cacheDir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $cacheDir));
    }
}

$configGenerator = new ConfigAggregator(
    [
        new PhpFileProvider('config/autoload/*'),
        new PhpFileProvider('config/' . $consumerApi->slug() . '/*'),
    ],
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
$app->add(MeasureRequest::class);


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
$app->add(LimitJsonBody::class);

// Add Routing Middleware
$app->addRoutingMiddleware();


// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response        = $app->handle($request);
$responseEmitter = new ResponseEmitter($headerSettings);
$responseEmitter->emit($response);
