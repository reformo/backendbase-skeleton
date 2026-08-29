<?php

declare(strict_types=1);

return [
    'redis' => [
        'host' => backendbaseEnv('BACKENDBASE_REDIS_HOST', '127.0.0.1'),
        'port' => backendbaseIntegerEnvironmentValue('BACKENDBASE_REDIS_PORT', 6379),
    ],
];
