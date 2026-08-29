<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use UnexpectedValueException;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * @phpstan-type DatabaseSettings array{connect: string}
 * @phpstan-type ReadinessSettings array{timeoutSeconds: float}
 * @phpstan-type RedisSettings array{host: string, port: int}
 * @phpstan-type JwtValues array{
 *     alias: string,
 *     issuer: non-empty-string,
 *     permitted-for: non-empty-string,
 *     sign-key: non-empty-string,
 *     duration: string
 * }
 * @phpstan-type RuntimeSettings array{
 *     basePath: string,
 *     cdnBaseUrl: string,
 *     displayErrorDetails: bool,
 *     logError: bool,
 *     logErrorDetails: bool,
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
        $timeoutSeconds = is_array($settings) ? ($settings['timeoutSeconds'] ?? null) : null;
        if (! is_float($timeoutSeconds) && ! is_int($timeoutSeconds)) {
            throw new UnexpectedValueException('The readiness settings are invalid.');
        }

        return ['timeoutSeconds' => (float) $timeoutSeconds];
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

        return ['host' => $settings['host'], 'port' => $settings['port']];
    }

    /** @return JwtValues */
    public static function jwt(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || ! is_string($settings['alias'] ?? null)
            || ! is_string($settings['issuer'] ?? null)
            || $settings['issuer'] === ''
            || ! is_string($settings['permitted-for'] ?? null)
            || $settings['permitted-for'] === ''
            || ! is_string($settings['sign-key'] ?? null)
            || $settings['sign-key'] === ''
            || ! is_string($settings['duration'] ?? null)
        ) {
            throw new UnexpectedValueException('The JWT settings are invalid.');
        }

        return [
            'alias' => $settings['alias'],
            'issuer' => $settings['issuer'],
            'permitted-for' => $settings['permitted-for'],
            'sign-key' => $settings['sign-key'],
            'duration' => $settings['duration'],
        ];
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
            $cdnBaseUrl          = $settings['cdnBaseUrl'] ?? '';
            $displayErrorDetails = $settings['displayErrorDetails'] ?? false;
            $logError            = $settings['logError'] ?? true;
            $logErrorDetails     = $settings['logErrorDetails'] ?? true;
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
            'cdnBaseUrl' => $cdnBaseUrl,
            'displayErrorDetails' => $displayErrorDetails,
            'logError' => $logError,
            'logErrorDetails' => $logErrorDetails,
            'routeCacheFile' => $routeCacheFile,
        ];
    }
}
