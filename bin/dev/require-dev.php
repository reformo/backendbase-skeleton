<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;

require_once __DIR__ . '/../../vendor/autoload.php';

$failureMessage = 'Commands under bin/dev require BACKENDBASE_ENV=dev.';

try {
    Dotenv::createUnsafeImmutable(dirname(__DIR__, 2))->safeLoad();
    if (backendbaseEnv('BACKENDBASE_ENV') === 'dev') {
        return;
    }
} catch (InvalidFileException) {
    $failureMessage = 'Cannot load development settings. Check the local .env file.';
}

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, $failureMessage . "\n");
    exit(1);
}

http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo $failureMessage . "\n";
exit(1);
