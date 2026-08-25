<?php

declare(strict_types=1);

use Backendbase\Shared\Options\System\Environment;

$environment      = backendbaseEnv('BACKENDBASE_ENV', Environment::DEV->value);
$backendbaseDebug = backendbaseEnv('BACKENDBASE_LOGGER_LEVEL') ?? backendbaseEnv('BACKENDBASE_DEBUG');
$readinessTimeout = filter_var(
    backendbaseEnv('BACKENDBASE_READINESS_TIMEOUT_SECONDS', 2),
    FILTER_VALIDATE_FLOAT,
);
if ($readinessTimeout === false || $readinessTimeout < 0.1 || $readinessTimeout > 10.0) {
    throw new UnexpectedValueException('The readiness timeout must be between 0.1 and 10 seconds.');
}

return [
    'service-name' => backendbaseEnv('BACKENDBASE_SERVICE_NAME', 'example'),
    'env' => $environment,
    'displayErrorDetails' => $environment !== Environment::PRODUCTION->value,
    'logError'            => true,
    'logErrorDetails'     => true,
    'cdnBaseUrl' => backendbaseEnv('CDN_BASE_URL', ''),
    'use-cache' => true,
    'doctrine' => [
        'connect' => backendbaseEnv('BACKENDBASE_DB_DSN'),
    ],
    'readiness' => ['timeoutSeconds' => $readinessTimeout],
    'base-path' => backendbaseEnv('BASE_PATH', ''),

    'payment-gateway-call-baseurl' => backendbaseEnv('PAYMENT_CALLBACK_BASEURL', ''),

];
