<?php

declare(strict_types=1);

return [
    'identity' => [
        'tokenLifetimeSeconds' => backendbaseIntegerEnvironmentValue('BACKENDBASE_IDENTITY_TOKEN_LIFETIME_SECONDS', 900),
        'contactProofKeyId' => backendbaseEnv('BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY_ID', backendbaseEnv('BACKENDBASE_IDENTITY_ENCRYPTION_KEY_ID', 'current')),
        'contactProofKey' => backendbaseEnv('BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY', backendbaseEnv('BACKENDBASE_IDENTITY_ENCRYPTION_KEY', '')),
        'previousContactProofKeyId' => backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_CONTACT_PROOF_KEY_ID', backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_ENCRYPTION_KEY_ID', '')),
        'previousContactProofKey' => backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_CONTACT_PROOF_KEY', backendbaseEnv('BACKENDBASE_IDENTITY_PREVIOUS_ENCRYPTION_KEY', '')),
        'contactEmailSender' => backendbaseEnv('BACKENDBASE_IDENTITY_CONTACT_EMAIL_SENDER', ''),
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
