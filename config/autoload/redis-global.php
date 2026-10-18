<?php

declare(strict_types=1);

return [
    'redis' => [
        'host' => backendbaseEnv('BACKENDBASE_REDIS_HOST', '127.0.0.1'),
        'port' => backendbaseIntegerEnvironmentValue('BACKENDBASE_REDIS_PORT', 6379),
        'username' => backendbaseEnv('BACKENDBASE_REDIS_USERNAME', ''),
        'password' => backendbaseEnv('BACKENDBASE_REDIS_PASSWORD', ''),
        'tls' => match (backendbaseEnv('BACKENDBASE_REDIS_TLS', 'false')) {
            'true' => true,
            'false' => false,
            default => throw new UnexpectedValueException('BACKENDBASE_REDIS_TLS must be true or false.'),
        },
    ],
];
