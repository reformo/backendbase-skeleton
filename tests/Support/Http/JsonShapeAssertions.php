<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use PHPUnit\Framework\Assert;
use stdClass;

use function is_array;
use function is_float;
use function is_int;

final class JsonShapeAssertions
{
    /** @param array<string, mixed> $schema */
    public static function assertMatches(mixed $value, array $schema): void
    {
        if ($value === null && ($schema['nullable'] ?? false) === true) {
            return;
        }

        if (isset($schema['enum'])) {
            Assert::assertIsArray($schema['enum']);
            Assert::assertContains($value, $schema['enum']);
        }

        if (($schema['type'] ?? null) === 'object') {
            self::assertObject($value, $schema);

            return;
        }

        if (($schema['type'] ?? null) === 'array') {
            self::assertArray($value, $schema);

            return;
        }

        self::assertScalar($value, $schema['type'] ?? null);
    }

    private static function assertScalar(mixed $value, mixed $type): void
    {
        match ($type) {
            'string' => Assert::assertIsString($value),
            'integer' => Assert::assertIsInt($value),
            'number' => Assert::assertTrue(is_int($value) || is_float($value)),
            'boolean' => Assert::assertIsBool($value),
            default => Assert::fail('A response field must declare a supported JSON type.'),
        };
    }

    /** @param array<string, mixed> $schema */
    private static function assertObject(mixed $value, array $schema): void
    {
        Assert::assertInstanceOf(stdClass::class, $value);
        $fields   = (array) $value;
        $required = $schema['required'] ?? [];
        Assert::assertIsArray($required);
        foreach ($required as $name) {
            Assert::assertIsString($name);
            Assert::assertArrayHasKey($name, $fields);
        }

        foreach ($fields as $name => $field) {
            self::assertField($name, $field, $schema);
        }
    }

    /** @param array<string, mixed> $schema */
    private static function assertField(string|int $name, mixed $value, array $schema): void
    {
        $properties = $schema['properties'] ?? [];
        Assert::assertIsArray($properties);
        if (isset($properties[$name])) {
            Assert::assertIsArray($properties[$name]);
            self::assertMatches($value, $properties[$name]);

            return;
        }

        $additional = $schema['additionalProperties'] ?? true;
        Assert::assertNotSame(false, $additional, 'An undeclared response field was returned: ' . $name);
        if (! is_array($additional)) {
            return;
        }

        self::assertMatches($value, $additional);
    }

    /** @param array<string, mixed> $schema */
    private static function assertArray(mixed $value, array $schema): void
    {
        Assert::assertIsArray($value);
        $items = $schema['items'] ?? null;
        Assert::assertIsArray($items);
        foreach ($value as $item) {
            self::assertMatches($item, $items);
        }
    }
}
