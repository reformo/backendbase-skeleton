<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\CQRS\RegistryHandlerResolver;
use Backendbase\Shared\CQRS\HandlerResolver;
use DI\ContainerBuilder;

use function DI\get;

include_once __DIR__ . '/doctrine-types.php';

/** @param class-string<HandlerResolver> $resolverClass */
return static function (ContainerBuilder $containerBuilder, string $resolverClass = RegistryHandlerResolver::class): void {
    $providerFiles = [
        'modules.php',
        'doctrine.php',
        'redis.php',
        'logger.php',
        'aws.php',
        'notification.php',
        'rabbitmq.php',
        'queue.php',
        'readiness.php',
        'application.php',
        'bounded-contexts.php',
    ];

    foreach ($providerFiles as $providerFile) {
        $provider = require __DIR__ . '/dependencies/' . $providerFile;
        $provider($containerBuilder);
    }

    $containerBuilder->addDefinitions([HandlerResolver::class => get($resolverClass)]);
};
