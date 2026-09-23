<?php

declare(strict_types=1);

return [
    'notification' => [
        'sms' => [
            'driver' => backendbaseEnv('BACKENDBASE_SMS_DRIVER', 'sns'),
            'twilio' => [
                'accountSid' => backendbaseEnv('TWILIO_ACCOUNT_SID', ''),
                'authToken' => backendbaseEnv('TWILIO_AUTH_TOKEN', ''),
                'from' => backendbaseEnv('TWILIO_FROM', ''),
                'timeoutSeconds' => backendbaseFloatEnvironmentValue('TWILIO_TIMEOUT_SECONDS', 5.0),
            ],
            'netgsm' => [
                'username' => backendbaseEnv('NETGSM_USERNAME', ''),
                'password' => backendbaseEnv('NETGSM_PASSWORD', ''),
                'sender' => backendbaseEnv('NETGSM_SENDER', ''),
                'encoding' => backendbaseEnv('NETGSM_ENCODING', 'TR'),
                'timeoutSeconds' => backendbaseFloatEnvironmentValue('NETGSM_TIMEOUT_SECONDS', 5.0),
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
                'timeoutSeconds' => backendbaseFloatEnvironmentValue('SMTP_TIMEOUT_SECONDS', 5.0),
            ],
        ],
        'push' => [
            'projectId' => backendbaseEnv('FIREBASE_PROJECT_ID', ''),
            'credentialsPath' => backendbaseEnv('FIREBASE_CREDENTIALS_PATH', ''),
            'cdnBaseUrl' => backendbaseEnv('CDN_BASE_URL', ''),
            'timeoutSeconds' => backendbaseFloatEnvironmentValue('FIREBASE_TIMEOUT_SECONDS', 5.0),
        ],
    ],
];
