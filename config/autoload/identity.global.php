<?php

declare(strict_types=1);

return [
    'identity' => [
        'tokenLifetimeSeconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_IDENTITY_TOKEN_LIFETIME_SECONDS', 900),
        'encryptionKeyId' => backendbaseEnv('BACKENDBASE_IDENTITY_ENCRYPTION_KEY_ID', 'current'),
        'encryptionKey' => backendbaseEnv('BACKENDBASE_IDENTITY_ENCRYPTION_KEY', ''),
        'previousEncryptionKeyId' => backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_ENCRYPTION_KEY_ID', ''),
        'previousEncryptionKey' => backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_ENCRYPTION_KEY', ''),
        'ownershipSender' => backendbaseEnv('BACKENDBASE_IDENTITY_OWNERSHIP_SENDER', ''),
        'USER' => [
            'issuer' => backendbaseEnv('BACKENDBASE_USER_JWT_ISSUER', ''),
            'audience' => backendbaseEnv('BACKENDBASE_USER_JWT_AUDIENCE', ''),
            'keyId' => backendbaseEnv('BACKENDBASE_USER_JWT_KEY_ID', 'current'),
            'key' => backendbaseEnv('BACKENDBASE_USER_JWT_KEY', ''),
            'previousKeyId' => backendbaseEnv('BACKENDBASE_USER_JWT_PREVIOUS_KEY_ID', ''),
            'previousKey' => backendbaseEnv('BACKENDBASE_USER_JWT_PREVIOUS_KEY', ''),
        ],
        'ADMIN' => [
            'issuer' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_ISSUER', ''),
            'audience' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_AUDIENCE', ''),
            'keyId' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_KEY_ID', 'current'),
            'key' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_KEY', ''),
            'previousKeyId' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_PREVIOUS_KEY_ID', ''),
            'previousKey' => backendbaseEnv('BACKENDBASE_ADMIN_JWT_PREVIOUS_KEY', ''),
        ],
    ],
];
