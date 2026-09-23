<?php

declare(strict_types=1);

use DI\ContainerBuilder;

include_once __DIR__ . '/doctrine-types.php';

return static function (ContainerBuilder $containerBuilder): void {
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
};
