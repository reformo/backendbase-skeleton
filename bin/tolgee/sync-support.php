<?php

declare(strict_types=1);

namespace Backendbase\TolgeeSync;

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use JsonException;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** @return array{root: string, directory: string, service: string, client: Client} */
function context(): array
{
    $root = dirname(__DIR__, 2);
    Dotenv::createUnsafeImmutable($root)->safeLoad();

    $config = require $root . '/config/autoload/global.php';
    $service = $config['service-name'] ?? null;
    $apiKey = backendbaseEnv('TOLGEE_API_KEY');
    $apiUrl = backendbaseEnv('TOLGEE_API_URL', 'https://app.tolgee.io');

    if (! is_string($service) || trim($service) === '') {
        throw new RuntimeException('config/autoload/global.php must define a non-empty "service-name".');
    }

    if (! is_string($apiKey) || trim($apiKey) === '') {
        throw new RuntimeException('TOLGEE_API_KEY is required.');
    }

    if (! is_string($apiUrl) || filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('TOLGEE_API_URL must be a valid URL.');
    }

    return [
        'root' => $root,
        'directory' => $root . '/resources/i18n',
        'service' => trim($service),
        'client' => new Client([
            'base_uri' => rtrim($apiUrl, '/') . '/',
            'connect_timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => trim($apiKey),
            ],
            'http_errors' => false,
            'timeout' => 30,
        ]),
    ];
}

function runLocalSync(): int
{
    return runSafely(static function (): void {
        $context = context();
        $translationsByKey = [];

        foreach (translationFiles($context['directory']) as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME);

            foreach (flatten(loadTranslationFile($file), $file) as $key => $translation) {
                $translationsByKey[$context['service'] . '.' . $key][$locale] = $translation;
            }
        }

        ksort($translationsByKey);
        $remoteKeys = array_fill_keys(getRemoteKeyNames($context['client']), true);
        $created = 0;

        foreach ($translationsByKey as $key => $translations) {
            if (isset($remoteKeys[$key])) {
                continue;
            }

            ksort($translations);
            requestJson($context['client'], 'POST', 'v2/projects/keys', [
                'json' => [
                    'name' => $key,
                    'isPlural' => false,
                    'translations' => $translations,
                ],
            ], [201]);
            fwrite(STDOUT, sprintf("Created %s\n", $key));
            ++$created;
        }

        fwrite(STDOUT, sprintf(
            "Checked %d local translation keys; created %d missing Tolgee keys.\n",
            count($translationsByKey),
            $created,
        ));
    });
}

function runRemoteSync(): int
{
    return runSafely(static function (): void {
        $context = context();
        $prefix = $context['service'] . '.';
        $renderedFiles = [];

        foreach (translationFiles($context['directory']) as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME);
            $localTranslations = [];

            foreach (exportRemoteTranslations($context['client'], $locale, $prefix) as $key => $translation) {
                if (! str_starts_with($key, $prefix)) {
                    continue;
                }

                $localKey = substr($key, strlen($prefix));

                if ($localKey === '') {
                    throw new RuntimeException(sprintf(
                        'Tolgee key "%s" does not contain a local translation path.',
                        $key,
                    ));
                }

                $localTranslations[$localKey] = $translation;
            }

            if ($localTranslations === []) {
                throw new RuntimeException(sprintf(
                    'Tolgee returned no "%s*" translations for locale "%s"; no files were changed.',
                    $prefix,
                    $locale,
                ));
            }

            $renderedFiles[$file] = renderPhpFile(expand($localTranslations));
        }

        foreach ($renderedFiles as $file => $contents) {
            writeAtomically($file, $contents);
            fwrite(STDOUT, sprintf("Updated %s\n", $file));
        }

        fwrite(STDOUT, sprintf(
            "Updated %d locale files from Tolgee keys prefixed with \"%s\".\n",
            count($renderedFiles),
            $prefix,
        ));
    });
}

/** @return list<string> */
function getRemoteKeyNames(Client $client): array
{
    $page = 0;
    $names = [];

    do {
        $response = requestJson($client, 'GET', 'v2/projects/keys', [
            'query' => ['page' => $page, 'size' => 100, 'sort' => 'id,ASC'],
        ]);
        $keys = $response['_embedded']['keys'] ?? [];

        if (! is_array($keys)) {
            throw new RuntimeException('Tolgee returned an invalid keys response.');
        }

        foreach ($keys as $key) {
            if (
                ! is_array($key)
                || ! is_string($key['name'] ?? null)
                || ! in_array($key['namespace'] ?? null, [null, ''], true)
            ) {
                continue;
            }

            $names[] = $key['name'];
        }

        $totalPages = $response['page']['totalPages'] ?? null;
        ++$page;
    } while (is_int($totalPages) ? $page < $totalPages : count($keys) === 100);

    return $names;
}

/** @return array<string, string> */
function exportRemoteTranslations(Client $client, string $locale, string $prefix): array
{
    $query = http_build_query([
        'languages' => $locale,
        'format' => 'JSON',
        'structureDelimiter' => '',
        'filterKeyPrefix' => $prefix,
        'zip' => 'false',
        'supportArrays' => 'false',
    ], '', '&', PHP_QUERY_RFC3986);
    $translations = requestJson($client, 'GET', 'v2/projects/export?' . $query);

    foreach ($translations as $key => $translation) {
        if (! is_string($key) || ! is_string($translation)) {
            throw new RuntimeException(sprintf(
                'Tolgee returned a non-string translation for locale "%s".',
                $locale,
            ));
        }
    }

    /** @var array<string, string> $translations */
    return $translations;
}

/**
 * @param array<string, mixed> $options
 * @param list<int> $successStatuses
 *
 * @return array<string, mixed>
 */
function requestJson(
    Client $client,
    string $method,
    string $uri,
    array $options = [],
    array $successStatuses = [200],
): array {
    $response = $client->request($method, $uri, $options);
    $status = $response->getStatusCode();
    $body = (string) $response->getBody();

    if (! in_array($status, $successStatuses, true)) {
        $error = json_decode($body, true);
        $message = is_array($error) && is_string($error['code'] ?? null)
            ? $error['code']
            : trim($body);

        throw new RuntimeException(sprintf(
            'Tolgee API request failed with HTTP %d: %s',
            $status,
            substr($message !== '' ? $message : $response->getReasonPhrase(), 0, 500),
        ));
    }

    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Tolgee returned invalid JSON.', previous: $exception);
    }

    if (! is_array($decoded)) {
        throw new RuntimeException('Tolgee returned an unexpected JSON response.');
    }

    return $decoded;
}

/**
 * @param array<array-key, mixed> $values
 *
 * @return array<string, string>
 */
function flatten(array $values, string $source, string $prefix = ''): array
{
    $flattened = [];

    foreach ($values as $key => $value) {
        $segment = (string) $key;
        $path = $prefix === '' ? $segment : $prefix . '.' . $segment;

        if ($segment === '' || str_contains($segment, '.')) {
            throw new RuntimeException(sprintf(
                'Translation file "%s" contains an empty key or a key containing ".".',
                $source,
            ));
        }

        if (is_array($value)) {
            $flattened += flatten($value, $source, $path);
            continue;
        }

        if (! is_string($value)) {
            throw new RuntimeException(sprintf('Translation "%s" in "%s" must be a string.', $path, $source));
        }

        $flattened[$path] = $value;
    }

    return $flattened;
}

/**
 * @param array<string, string> $translations
 *
 * @return array<array-key, mixed>
 */
function expand(array $translations): array
{
    ksort($translations);
    $expanded = [];

    foreach ($translations as $key => $translation) {
        $segments = explode('.', $key);

        if (in_array('', $segments, true)) {
            throw new RuntimeException(sprintf('Translation key "%s" contains an empty path segment.', $key));
        }

        $leaf = array_pop($segments);
        $cursor = &$expanded;

        foreach ($segments as $segment) {
            if (isset($cursor[$segment]) && ! is_array($cursor[$segment])) {
                throw new RuntimeException(sprintf('Translation key "%s" conflicts with a leaf key.', $key));
            }

            $cursor[$segment] ??= [];
            $cursor = &$cursor[$segment];
        }

        if (isset($cursor[$leaf]) && is_array($cursor[$leaf])) {
            throw new RuntimeException(sprintf('Translation key "%s" conflicts with a nested key.', $key));
        }

        $cursor[$leaf] = $translation;
        unset($cursor);
    }

    return $expanded;
}

/** @param array<array-key, mixed> $translations */
function renderPhpFile(array $translations): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nreturn "
        . var_export($translations, true)
        . ";\n";
}

/** @return list<string> */
function translationFiles(string $directory): array
{
    $files = is_dir($directory) ? glob($directory . '/*.php', GLOB_NOSORT) : false;

    if ($files === false || $files === []) {
        throw new RuntimeException(sprintf('No PHP translation files found in "%s".', $directory));
    }

    sort($files);

    return array_values($files);
}

/** @return array<array-key, mixed> */
function loadTranslationFile(string $file): array
{
    $translations = (static fn (): mixed => require $file)();

    if (! is_array($translations)) {
        throw new RuntimeException(sprintf('Translation file "%s" must return an array.', $file));
    }

    return $translations;
}

function writeAtomically(string $file, string $contents): void
{
    $temporaryFile = tempnam(dirname($file), '.tolgee-');

    if ($temporaryFile === false) {
        throw new RuntimeException(sprintf('Could not create a temporary file beside "%s".', $file));
    }

    try {
        if (file_put_contents($temporaryFile, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write temporary file for "%s".', $file));
        }

        $permissions = fileperms($file);

        if ($permissions !== false) {
            chmod($temporaryFile, $permissions & 0777);
        }

        if (! rename($temporaryFile, $file)) {
            throw new RuntimeException(sprintf('Could not replace translation file "%s".', $file));
        }
    } finally {
        if (is_file($temporaryFile)) {
            // tempnam created this local temporary file.
            // nosemgrep: php.lang.security.unlink-use.unlink-use
            unlink($temporaryFile);
        }
    }
}

function printHelp(string $command, string $description): void
{
    fwrite(STDOUT, <<<HELP
    {$description}

    Usage:
      {$command}

    Environment:
      TOLGEE_API_KEY  Project API key (required)
      TOLGEE_API_URL  Tolgee base URL (default: https://app.tolgee.io)

    HELP);
}

/** @param callable(): void $operation */
function runSafely(callable $operation): int
{
    try {
        $operation();

        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n");

        return 1;
    }
}
