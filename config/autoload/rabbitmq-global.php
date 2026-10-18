<?php

declare(strict_types=1);

return [
    'rabbitmq' => [
        'host' => backendbaseEnv('BACKENDBASE_RABBITMQ_HOST', '127.0.0.1'),
        'port' => backendbaseIntegerEnvironmentValue('BACKENDBASE_RABBITMQ_PORT', 5672),
        'user' => backendbaseEnv('BACKENDBASE_RABBITMQ_USER', 'backendbase'),
        'password' => backendbaseEnv('BACKENDBASE_RABBITMQ_PASSWORD', 'backendbase'),
        'vhost' => backendbaseEnv('BACKENDBASE_RABBITMQ_VHOST', '/'),
        'queue' => backendbaseEnv('BACKENDBASE_RABBITMQ_QUEUE_NAME', 'backendbase-queue'),
        'exchange' => backendbaseEnv('BACKENDBASE_RABBITMQ_EXCHANGE_NAME', 'backendbase'),
        'exchange-type' => backendbaseEnv('BACKENDBASE_RABBITMQ_EXCHANGE_TYPE', 'direct'),
        'dead-letter-exchange' => backendbaseEnv('BACKENDBASE_RABBITMQ_DEAD_LETTER_EXCHANGE_NAME', 'backendbase.dead-letter'),
        'dead-letter-queue-suffix' => backendbaseEnv('BACKENDBASE_RABBITMQ_DEAD_LETTER_QUEUE_SUFFIX', '.dead-letter'),
        'message-retention-milliseconds' => 604800000,
        'connection-timeout' => backendbaseFloatEnvironmentValue('BACKENDBASE_RABBITMQ_CONNECTION_TIMEOUT_SECONDS', 3.0),
        'read-write-timeout' => backendbaseFloatEnvironmentValue('BACKENDBASE_RABBITMQ_READ_WRITE_TIMEOUT_SECONDS', 35.0),
        'heartbeat' => backendbaseIntegerEnvironmentValue('BACKENDBASE_RABBITMQ_HEARTBEAT_SECONDS', 15),
        'prefetch-count' => backendbaseIntegerEnvironmentValue('BACKENDBASE_RABBITMQ_PREFETCH_COUNT', 1),
    ],
];
