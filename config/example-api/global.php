<?php

declare(strict_types=1);

return [
    'example-api' => [
        'version' => '1.0.0',
        'api-key' => backendbaseEnv('EXAMPLE_API_KEY'),
    ],
    'route-cache-file' => 'var/cache/example-api/routes.php',
];
