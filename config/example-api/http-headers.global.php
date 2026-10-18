<?php

declare(strict_types=1);

return [
    'headers' => [
        'Access-Control-Allow-Origin'         => backendbaseEnv('EXAMPLE_API_CORS_ORIGIN', 'http://127.0.0.1:8080'),
        'Access-Control-Allow-Headers'        => 'X-Requested-With, Content-Type, Accept, Accept-Language, Origin, Content-Disposition, Authorization, Backendbase-Api-Key, The-Timezone-IANA, X-Source-Id, X-Request-Id, Idempotency-Key',
    ],
];
