<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\Serialization;

use ArrayObject;
use stdClass;

use function array_map;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class JsonPayload
{
    public static function decode(string $json): mixed
    {
        $value = self::preserveObjects(json_decode($json, false, 512, JSON_THROW_ON_ERROR));

        return $value instanceof ArrayObject ? $value->getArrayCopy() : $value;
    }

    private static function preserveObjects(mixed $value): mixed
    {
        if (! is_array($value) && ! $value instanceof stdClass) {
            return $value;
        }

        $members = array_map(self::preserveObjects(...), (array) $value);

        // Iterable objects support carrier mapping and preserve object shapes inside mixed values.
        return $value instanceof stdClass ? new ArrayObject($members) : $members;
    }
}
