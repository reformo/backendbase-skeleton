<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain\Primitives;

use Backendbase\Shared\Exception\InvalidUserInput;
use JsonException;
use stdClass;

use function array_filter;
use function array_keys;
use function assert;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

final readonly class EntryDetails
{
    private const int MAX_DEPTH = 64;

    private string $json;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        if (array_filter(array_keys($values), is_int(...)) !== []) {
            throw InvalidUserInput::create('The details value cannot use integer member names.');
        }

        try {
            $this->json = json_encode((object) $values, JSON_THROW_ON_ERROR, self::MAX_DEPTH);
            $this->toArray();
        } catch (JsonException $exception) {
            throw InvalidUserInput::create('The details value must contain valid JSON.', previous: $exception);
        }

        $this->validateMemberNames($values);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $object = json_decode($this->json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        assert($object instanceof stdClass);

        /** @var array<string, mixed> $values */
        $values = (array) $object;

        return $values;
    }

    /** @param array<array-key, mixed> $values */
    private function validateMemberNames(array $values): void
    {
        if (array_filter(array_keys($values), static fn (int|string $key): bool => is_string($key) && str_starts_with($key, "\0")) !== []) {
            throw InvalidUserInput::create('The details value cannot use NUL-prefixed member names.');
        }

        foreach ($values as $value) {
            $this->validateNestedMemberNames($value);
        }
    }

    private function validateNestedMemberNames(mixed $value): void
    {
        if (! is_array($value) && ! $value instanceof stdClass) {
            return;
        }

        $this->validateMemberNames((array) $value);
    }
}
