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
            'queue' => backendbaseEnv('AWS_SQS_QUEUE', 'backendbase-queue'),
            'queueUrl' => backendbaseEnv('AWS_SQS_QUEUE_URL', ''),
            'maxNumberOfMessages' => (int) backendbaseEnv('AWS_SQS_MAX_NUMBER_OF_MESSAGES', 10),
            'waitTimeSeconds' => (int) backendbaseEnv('AWS_SQS_WAIT_TIME_SECONDS', 20),
            'visibilityTimeout' => (int) backendbaseEnv('AWS_SQS_VISIBILITY_TIMEOUT', 30),
            'continuous' => filter_var(
                backendbaseEnv('AWS_SQS_CONTINUOUS', 'true'),
                FILTER_VALIDATE_BOOL,
            ),
        ],
        'sns' => [
            'smsType' => backendbaseEnv('AWS_SNS_SMS_TYPE', 'Transactional'),
            'senderId' => backendbaseEnv('AWS_SNS_SENDER_ID', ''),
        ],
    ],
];
