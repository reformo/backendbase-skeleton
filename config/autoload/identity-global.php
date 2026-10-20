<?php

declare(strict_types=1);

return [
    'identity' => [
        'registration' => [
            'require-mobile-verification' => backendbaseEnv('BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_MOBILE_VERIFICATION', 'true'),
            'require-email-verification' => backendbaseEnv('BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_EMAIL_VERIFICATION', 'false'),
        ],
        'contact-proof-key-id' => backendbaseEnv('BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY_ID', backendbaseEnv('BACKENDBASE_IDENTITY_ENCRYPTION_KEY_ID', 'current')),
        'contact-proof-key' => backendbaseEnv('BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY', backendbaseEnv('BACKENDBASE_IDENTITY_ENCRYPTION_KEY', '')),
        'previous-contact-proof-key-id' => backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_CONTACT_PROOF_KEY_ID', backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_ENCRYPTION_KEY_ID', '')),
        'previous-contact-proof-key' => backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_CONTACT_PROOF_KEY', backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_ENCRYPTION_KEY', '')),
        'contact-email-sender' => backendbaseEnv('BACKENDBASE_IDENTITY_CONTACT_EMAIL_SENDER', ''),
        'user' => [
            'access-token-lifetime-seconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_USER_ACCESS_TOKEN_LIFETIME_SECONDS', 900),
            'session-idle-timeout-seconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_USER_SESSION_IDLE_TIMEOUT_SECONDS', 86400),
            'session-maximum-lifetime-seconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_USER_SESSION_MAXIMUM_LIFETIME_SECONDS', 604800),
            'issuer' => backendbaseEnv('BACKENDBASE_USER_JWT_ISSUER', ''),
            'audience' => backendbaseEnv('BACKENDBASE_USER_JWT_AUDIENCE', ''),
            'key-id' => backendbaseEnv('BACKENDBASE_USER_JWT_KEY_ID', 'current'),
            'key' => backendbaseEnv('BACKENDBASE_USER_JWT_KEY', ''),
            'previous-key-id' => backendbaseEnv('BACKENDBASE_USER_JWT_PREVIOUS_KEY_ID', ''),
            'previous-key' => backendbaseEnv('BACKENDBASE_USER_JWT_PREVIOUS_KEY', ''),
        ],
        'admin' => [
            'access-token-lifetime-seconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_ADMIN_ACCESS_TOKEN_LIFETIME_SECONDS', 300),
            'session-idle-timeout-seconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_ADMIN_SESSION_IDLE_TIMEOUT_SECONDS', 1800),
            'session-maximum-lifetime-seconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_ADMIN_SESSION_MAXIMUM_LIFETIME_SECONDS', 28800),
            'issuer' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_ISSUER', ''),
            'audience' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_AUDIENCE', ''),
            'key-id' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_KEY_ID', 'current'),
            'key' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_KEY', ''),
            'previous-key-id' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_PREVIOUS_KEY_ID', ''),
            'previous-key' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_PREVIOUS_KEY', ''),
        ],
    ],
];
