<?php

declare(strict_types=1);

return [
    'notification' => [
        'sms' => [
            'driver' => backendbaseEnv('BACKENDBASE_SMS_DRIVER', 'sns'),
            'twilio' => [
                'account-sid' => backendbaseEnv('TWILIO_ACCOUNT_SID', ''),
                'auth-token' => backendbaseEnv('TWILIO_AUTH_TOKEN', ''),
                'from' => backendbaseEnv('TWILIO_FROM', ''),
                'timeout-seconds' => backendbaseFloatEnvironmentValue('TWILIO_TIMEOUT_SECONDS', 5.0),
            ],
            'netgsm' => [
                'username' => backendbaseEnv('NETGSM_USERNAME', ''),
                'password' => backendbaseEnv('NETGSM_PASSWORD', ''),
                'sender' => backendbaseEnv('NETGSM_SENDER', ''),
                'encoding' => backendbaseEnv('NETGSM_ENCODING', 'TR'),
                'timeout-seconds' => backendbaseFloatEnvironmentValue('NETGSM_TIMEOUT_SECONDS', 5.0),
            ],
        ],
        'email' => [
            'driver' => backendbaseEnv('BACKENDBASE_EMAIL_DRIVER', 'ses'),
            'smtp' => [
                'host' => backendbaseEnv('SMTP_HOST', ''),
                'port' => backendbaseIntegerEnvironmentValue('SMTP_PORT', 587),
                'username' => backendbaseEnv('SMTP_USERNAME', ''),
                'password' => backendbaseEnv('SMTP_PASSWORD', ''),
                'encryption' => backendbaseEnv('SMTP_ENCRYPTION', 'starttls'),
                'timeout-seconds' => backendbaseFloatEnvironmentValue('SMTP_TIMEOUT_SECONDS', 5.0),
            ],
        ],
        'push' => [
            'project-id' => backendbaseEnv('FIREBASE_PROJECT_ID', ''),
            'credentials-path' => backendbaseEnv('FIREBASE_CREDENTIALS_PATH', ''),
            'cdn-base-url' => backendbaseEnv('CDN_BASE_URL', ''),
            'timeout-seconds' => backendbaseFloatEnvironmentValue('FIREBASE_TIMEOUT_SECONDS', 5.0),
        ],
    ],
];
