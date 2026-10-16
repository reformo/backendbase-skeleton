<?php

declare(strict_types=1);

namespace Backendbase\MessageViewer;

use Closure;
use GuzzleHttp\ClientInterface;
use JsonException;
use RuntimeException;

use function array_find;
use function array_key_exists;
use function dirname;
use function file_get_contents;
use function header;
use function http_response_code;
use function in_array;
use function is_string;
use function json_encode;
use function parse_str;
use function parse_url;
use function preg_match;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/emails.php';
require_once __DIR__ . '/preview.php';

/**
 * @param array<string, mixed>                                                          $server
 * @param Closure(): list<array{timestamp: string, recipient: string, message: string}> $smsReader
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function route(array $server, ClientInterface $client, Closure $smsReader, int $awsPort): array
{
    if (! localRequest($server)) {
        return response(403, 'text/plain; charset=utf-8', 'Local requests only.');
    }

    if (($server['REQUEST_METHOD'] ?? '') !== 'GET') {
        return response(405, 'text/plain; charset=utf-8', 'Use GET to read the viewer.');
    }

    $uri = $server['REQUEST_URI'] ?? '/';
    if (! is_string($uri)) {
        return response(400, 'text/plain; charset=utf-8', 'Invalid request path.');
    }

    $path = parse_url($uri, PHP_URL_PATH);
    if (($server['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' && in_array($path, ['/messages', '/emails', '/email-preview'], true)) {
        return response(403, 'text/plain; charset=utf-8', 'Local requests only.');
    }

    try {
        return sourceResponse($uri, $client, $smsReader, $awsPort);
    } catch (RuntimeException $exception) {
        return sourceFailure($exception->getMessage(), $path === '/email-preview');
    } catch (JsonException) {
        return sourceFailure('MiniStack returned invalid message encoding.', $path === '/email-preview');
    }
}

/** @return array{status: int, headers: array<string, string>, body: string} */
function sourceFailure(string $message, bool $preview): array
{
    if ($preview) {
        return response(503, 'text/plain; charset=utf-8', $message, true);
    }

    return response(503, 'application/json', json_encode(['error' => $message], JSON_THROW_ON_ERROR));
}

/** @param array<string, mixed> $server */
function localRequest(array $server): bool
{
    $port = $server['SERVER_PORT'] ?? '';
    $host = $server['HTTP_HOST'] ?? '';
    if (! is_string($port)) {
        return false;
    }

    return in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && in_array($host, ['127.0.0.1:' . $port, 'localhost:' . $port], true);
}

/**
 * @param Closure(): list<array{timestamp: string, recipient: string, message: string}> $smsReader
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function sourceResponse(string $uri, ClientInterface $client, Closure $smsReader, int $awsPort): array
{
    $path = parse_url($uri, PHP_URL_PATH);
    if ($path === '/messages') {
        return response(200, 'application/json', json_encode(['messages' => $smsReader()], JSON_THROW_ON_ERROR));
    }

    if ($path === '/emails') {
        return response(200, 'application/json', json_encode(['emails' => readEmails($client, $awsPort)], JSON_THROW_ON_ERROR));
    }

    if ($path === '/email-preview') {
        return previewResponse($uri, $client, $awsPort);
    }

    $assets = ['/' => ['index.html', 'text/html'], '/viewer.css' => ['viewer.css', 'text/css'], '/viewer.js' => ['viewer.js', 'text/javascript']];
    if (! is_string($path) || ! array_key_exists($path, $assets)) {
        return response(404, 'text/plain; charset=utf-8', 'Not found.');
    }

    [$filename, $type] = $assets[$path];
    $body              = file_get_contents(dirname(__DIR__, 3) . '/resources/dev/message-viewer/' . $filename);
    if ($body === false) {
        throw new RuntimeException('The viewer asset is missing. Restore the local viewer files.');
    }

    return response(200, $type . '; charset=utf-8', $body);
}

/** @return array{status: int, headers: array<string, string>, body: string} */
function previewResponse(string $uri, ClientInterface $client, int $awsPort): array
{
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
    $identifier = $query['id'] ?? null;
    if (! is_string($identifier) || $identifier === '' || strlen($identifier) > 200) {
        return response(400, 'text/plain; charset=utf-8', 'Select a captured email.', true);
    }

    $emails = readEmails($client, $awsPort);
    $email  = array_find($emails, static fn (array $email): bool => $email['id'] === $identifier);
    if ($email === null) {
        return response(404, 'text/plain; charset=utf-8', 'This email is no longer in the captured list.', true);
    }

    return response(200, 'text/html; charset=utf-8', emailPreview($email['html'], $email['text']), true);
}

/** @return array{status: int, headers: array<string, string>, body: string} */
function response(int $status, string $type, string $body, bool $preview = false): array
{
    $policy = "default-src 'self'; frame-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";
    if ($preview) {
        $policy = "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'";
    }

    return [
        'status' => $status,
        'headers' => [
            'Content-Type' => $type,
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => $policy,
        ],
        'body' => $body,
    ];
}

/** @param array{status: int, headers: array<string, string>, body: string} $response */
function respond(array $response): void
{
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) {
        header($name . ': ' . $value);
    }

    echo $response['body'];
}

function awsPort(string $value): int
{
    if (preg_match('/\A[0-9]{1,5}\z/', $value) !== 1 || (int) $value < 1 || (int) $value > 65535) {
        throw new RuntimeException('BACKENDBASE_DEV_AWS_PORT must be an integer from 1 to 65535.');
    }

    return (int) $value;
}
