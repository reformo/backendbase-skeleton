<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use UnexpectedValueException;

use function is_array;
use function is_string;

/**
 * @phpstan-type HeaderSettings array{
 *     Access-Control-Allow-Origin: string,
 *     Access-Control-Allow-Headers: string
 * }
 * @phpstan-type ApiSettings array{api-key: string|null}
 */
final class ValidatedHttpSettings
{
    /** @return HeaderSettings */
    public static function headers(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || ! is_string($settings['Access-Control-Allow-Origin'] ?? null)
            || ! is_string($settings['Access-Control-Allow-Headers'] ?? null)
        ) {
            throw new UnexpectedValueException('The HTTP header settings are invalid.');
        }

        return [
            'Access-Control-Allow-Origin' => $settings['Access-Control-Allow-Origin'],
            'Access-Control-Allow-Headers' => $settings['Access-Control-Allow-Headers'],
        ];
    }

    /** @return ApiSettings */
    public static function api(mixed $settings): array
    {
        $apiKey = is_array($settings) ? ($settings['api-key'] ?? null) : null;
        if (! is_array($settings) || (! is_string($apiKey) && $apiKey !== null)) {
            throw new UnexpectedValueException('The API settings are invalid.');
        }

        return ['api-key' => $apiKey];
    }
}
