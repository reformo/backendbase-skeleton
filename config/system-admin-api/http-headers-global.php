<?php

declare(strict_types=1);

return [
    'headers' => [
        'Access-Control-Allow-Origin' => backendbaseEnv('SYSTEM_ADMIN_API_CORS_ORIGIN', 'http://127.0.0.1:8080'),
        'Access-Control-Allow-Headers' => 'Content-Type, Accept, Accept-Language, Origin, Authorization, X-Source-Id, X-Request-Id, Idempotency-Key, The-Timezone-IANA',
    ],
];
