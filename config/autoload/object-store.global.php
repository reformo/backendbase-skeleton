<?php

declare(strict_types=1);

$endpoint = backendbaseEnv('OBJECT_STORE_ENDPOINT');
if ($endpoint === null || $endpoint === '') {
    $endpoint = backendbaseEnv('AWS_ENDPOINT', '');
}

return [
    'objectStore' => [
        'credentials' => [
            'key' => backendbaseEnv('OBJECT_STORE_ACCESS_KEY', ''),
            'secret' => backendbaseEnv('OBJECT_STORE_SECRET_KEY', ''),
        ],
        'region' => backendbaseEnv('OBJECT_STORE_REGION', ''),
        'endpoint' => $endpoint,
        'bucket' => backendbaseEnv('BUCKET_NAME', ''),
        'cdnBaseUrl' => backendbaseEnv('CDN_BASE_URL'),
    ],
];
