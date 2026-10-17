<?php

declare(strict_types=1);

use Backendbase\Shared\Options\System\Environment;
use Monolog\Level;

$environment     = backendbaseEnv('BACKENDBASE_ENV', Environment::DEV->value);
$loggerLevelName = backendbaseEnv('BACKENDBASE_LOGGER_LEVEL') ?? backendbaseEnv('BACKENDBASE_DEBUG');

return [
    'logger' => [
        'name' => 'backendbase-app',
        'path' => backendbaseEnv('BACKENDBASE_LOG_TO_STDOUT') !== null ? 'php://stdout' : 'var/logs/app.log',
        'level' => $loggerLevelName !== null ? Level::{ucfirst((string) $loggerLevelName)} : Level::Info,
    ],
];
