<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Input;

use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeZone;
use Ramsey\Uuid\Uuid;

use function in_array;
use function is_string;
use function strlen;

final class RequestMetadata
{
    /** @param array<string, mixed> $server */
    public static function validate(array $server): void
    {
        self::traceId($server['HTTP_X_REQUEST_ID'] ?? null);
        self::timezone($server['HTTP_THE_TIMEZONE_IANA'] ?? null);
    }

    private static function traceId(mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || strlen($value) !== 36 || ! Uuid::isValid($value)) {
            throw InvalidUserInput::create('X-Request-Id must be a UUID.');
        }
    }

    private static function timezone(mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || $value === '' || strlen($value) > 128) {
            throw InvalidUserInput::create('The-Timezone-IANA must be a valid timezone name.');
        }

        if (! in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw InvalidUserInput::create('The-Timezone-IANA must be a valid timezone name.');
        }
    }
}
