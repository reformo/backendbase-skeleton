<?php

declare(strict_types=1);

return [
    'queue' => [
        'driver' => backendbaseEnv('BACKENDBASE_QUEUE_DRIVER', 'rabbitmq'),
    ],
    'aws' => [
        'credentials' => [
            'key' => backendbaseEnv('AWS_ACCESS_KEY_ID', ''),
            'secret' => backendbaseEnv('AWS_SECRET_ACCESS_KEY', backendbaseEnv('AWS_SECRET_KEY', '')),
        ],
        'region' => backendbaseEnv('AWS_REGION', 'eu-central-1'),
        'endpoint' => backendbaseEnv('AWS_ENDPOINT', ''),
        'sqs' => [
            'queue' => backendbaseEnv('AWS_SQS_QUEUE_NAME', 'backendbase-queue'),
            'queue-url' => backendbaseEnv('AWS_SQS_QUEUE_URL', ''),
            'maximum-message-count' => backendbaseIntegerEnvironmentValue('AWS_SQS_MAXIMUM_MESSAGE_COUNT', 10),
            'wait-time-seconds' => backendbaseIntegerEnvironmentValue('AWS_SQS_WAIT_TIME_SECONDS', 20),
            'visibility-timeout-seconds' => backendbaseIntegerEnvironmentValue('AWS_SQS_VISIBILITY_TIMEOUT_SECONDS', 30),
            'continuous' => filter_var(
                backendbaseEnv('AWS_SQS_CONTINUOUS', 'true'),
                FILTER_VALIDATE_BOOL,
            ),
        ],
        'sns' => [
            'sms-type' => backendbaseEnv('AWS_SNS_SMS_TYPE', 'Transactional'),
            'sender-id' => backendbaseEnv('AWS_SNS_SENDER_ID', ''),
        ],
    ],
];
