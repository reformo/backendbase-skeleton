<?php

declare(strict_types=1);

use Backendbase\Shared\Options\System\Environment;
use Monolog\Level;

$environment      = backendbaseEnv('BACKENDBASE_ENV', Environment::DEV->value);
$backendbaseDebug = backendbaseEnv('BACKENDBASE_LOGGER_LEVEL') ?? backendbaseEnv('BACKENDBASE_DEBUG');

return [
    'logger' => [
        'name' => 'backendbase-app',
        'path' => backendbaseEnv('docker') !== null ? 'php://stdout' : __DIR__ . '/../../var/logs/app.log',
        'level' => $backendbaseDebug !== null ? Level::{ucfirst((string) $backendbaseDebug)} : Level::Info,
    ],
];
