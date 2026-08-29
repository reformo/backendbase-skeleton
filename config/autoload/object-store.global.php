<?php

declare(strict_types=1);

return [
    'objectStore' => [
        'credentials' => [
            'key' => backendbaseEnv('OBJECT_STORE_ACCESS_KEY', ''),
            'secret' => backendbaseEnv('OBJECT_STORE_SECRET_KEY', ''),
        ],
        'region' => backendbaseEnv('OBJECT_STORE_REGION', ''),
        'endpoint' => backendbaseEnv('OBJECT_STORE_ENDPOINT', backendbaseEnv('AWS_ENDPOINT', '')),
        'bucket' => backendbaseEnv('BUCKET_NAME', ''),
        'cdnBaseUrl' => backendbaseEnv('CDN_BASE_URL'),
    ],
];
