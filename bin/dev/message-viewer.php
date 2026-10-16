<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use GuzzleHttp\Client;

use function Backendbase\MessageViewer\awsPort;
use function Backendbase\MessageViewer\readSms;
use function Backendbase\MessageViewer\respond;
use function Backendbase\MessageViewer\response;
use function Backendbase\MessageViewer\route;

require_once __DIR__ . '/message-viewer/http.php';

if (PHP_SAPI !== 'cli-server') {
    fwrite(STDERR, "Start the local viewer with composer message-viewer.\n");
    exit(1);
}

try {
    Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
    $port = awsPort((string) ($_ENV['BACKENDBASE_DEV_AWS_PORT'] ?? '4566'));
    respond(route($_SERVER, new Client(), readSms(...), $port));
} catch (RuntimeException $exception) {
    respond(response(503, 'application/json', json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)));
} catch (InvalidFileException) {
    respond(response(503, 'application/json', '{"error":"Cannot load viewer settings. Check the local .env file."}'));
}
