<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use UnexpectedValueException;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function str_contains;
use function strlen;

/**
 * @phpstan-type DatabaseSettings array{connect: string}
 * @phpstan-type ReadinessSettings array{'timeout-seconds': float}
 * @phpstan-type RedisSettings array{host: string, port: int, username: string, password: string, tls: bool}
 * @phpstan-type RuntimeSettings array{
 *     basePath: string,
 *     'cdn-base-url': string,
 *     'display-error-details': bool,
 *     'log-error': bool,
 *     'log-error-details': bool,
 *     routeCacheFile: string|null
 * }
 */
final class ValidatedApplicationSettings
{
    /** @return DatabaseSettings */
    public static function database(mixed $settings): array
    {
        if (! is_array($settings) || ! is_string($settings['connect'] ?? null)) {
            throw new UnexpectedValueException('The database settings are invalid.');
        }

        return ['connect' => $settings['connect']];
    }

    /** @return ReadinessSettings */
    public static function readiness(mixed $settings): array
    {
        $timeoutSeconds = is_array($settings) ? ($settings['timeout-seconds'] ?? null) : null;
        if (! is_float($timeoutSeconds) && ! is_int($timeoutSeconds)) {
            throw new UnexpectedValueException('The readiness settings are invalid.');
        }

        return ['timeout-seconds' => (float) $timeoutSeconds];
    }

    /** @return RedisSettings */
    public static function redis(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || ! is_string($settings['host'] ?? null)
            || ! is_int($settings['port'] ?? null)
        ) {
            throw new UnexpectedValueException('The Redis settings are invalid.');
        }

        $credentials = self::redisCredentials($settings);
        $tls         = $settings['tls'] ?? false;
        if ($settings['host'] === '' || preg_match('/[\x00-\x20\x7f]/', $settings['host']) === 1 || $settings['port'] < 1 || $settings['port'] > 65535 || ! is_bool($tls)) {
            throw new UnexpectedValueException('The Redis connection settings are invalid.');
        }

        return ['host' => $settings['host'], 'port' => $settings['port'], 'tls' => $tls, ...$credentials];
    }

    /**
     * @param array<array-key, mixed> $settings
     *
     * @return array{username: string, password: string}
     */
    private static function redisCredentials(array $settings): array
    {
        $username = $settings['username'] ?? '';
        $password = $settings['password'] ?? '';
        if (! is_string($username) || ! is_string($password)) {
            throw new UnexpectedValueException('The Redis credential settings are invalid.');
        }

        if (strlen($username) > 255 || strlen($password) > 1024 || str_contains($username, "\0") || str_contains($password, "\0") || ($username !== '' && $password === '')) {
            throw new UnexpectedValueException('The Redis credential settings are invalid.');
        }

        return ['username' => $username, 'password' => $password];
    }

    /** @return RuntimeSettings */
    public static function runtime(mixed $settings): array
    {
        $basePath            = null;
        $cdnBaseUrl          = null;
        $displayErrorDetails = null;
        $logError            = null;
        $logErrorDetails     = null;
        $routeCacheFile      = null;
        if (is_array($settings)) {
            $basePath            = $settings['base-path'] ?? '';
            $cdnBaseUrl          = $settings['cdn-base-url'] ?? '';
            $displayErrorDetails = $settings['display-error-details'] ?? false;
            $logError            = $settings['log-error'] ?? true;
            $logErrorDetails     = $settings['log-error-details'] ?? true;
            $routeCacheFile      = $settings['route-cache-file'] ?? null;
        }

        if (
            ! is_string($basePath)
            || ! is_string($cdnBaseUrl)
            || ! is_bool($displayErrorDetails)
            || ! is_bool($logError)
            || ! is_bool($logErrorDetails)
            || (! is_string($routeCacheFile) && $routeCacheFile !== null)
        ) {
            throw new UnexpectedValueException('The application runtime settings are invalid.');
        }

        return [
            'basePath' => $basePath,
            'cdn-base-url' => $cdnBaseUrl,
            'display-error-details' => $displayErrorDetails,
            'log-error' => $logError,
            'log-error-details' => $logErrorDetails,
            'routeCacheFile' => $routeCacheFile,
        ];
    }
}
