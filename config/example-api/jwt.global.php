<?php

declare(strict_types=1);

return [
    'jwt' => [
        'alias'         => 'USER',
        'issuer'        => 'backendbase-api',
        'identifier'    => 'H2i0W2e6llEU',
        'permitted-for' => backendbaseEnv('EXAMPLE_API_JWT_PERMITTED_FOR', 'example-api'),
        'sign-key'      => backendbaseEnv('BACKENDBASE_JWT_SIGN_KEY'),
        'duration'      => 'PT24H',
    ],
];
