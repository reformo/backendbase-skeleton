<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryListItem;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

use function array_keys;
use function ctype_digit;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class EntryReadModelMapper
{
    /** @param array<string, mixed> $row */
    public static function details(array $row): EntryDetails
    {
        $typeValue = self::string($row['type'] ?? null, 'type');
        $type      = EntryType::tryFrom($typeValue);
        if ($type === null) {
            throw new UnexpectedValueException('The persisted example type is invalid.');
        }

        return new EntryDetails(
            self::string($row['uuid'] ?? null, 'uuid'),
            $type,
            self::nullablePositiveInteger($row['type_target_id'] ?? null, 'type_target_id'),
            self::string($row['lookup_group'] ?? null, 'lookup_group'),
            self::string($row['lookup_key'] ?? null, 'lookup_key'),
            self::string($row['lookup_value'] ?? null, 'lookup_value'),
            self::jsonObject($row['details'] ?? null),
            self::boolean($row['is_active'] ?? null, 'is_active'),
            self::date($row['updated_at'] ?? null, 'updated_at'),
            self::date($row['created_at'] ?? null, 'created_at'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function listItem(array $row): EntryListItem
    {
        return new EntryListItem(
            self::string($row['uuid'] ?? null, 'uuid'),
            self::string($row['lookup_key'] ?? null, 'lookup_key'),
            self::string($row['lookup_value'] ?? null, 'lookup_value'),
            self::jsonObject($row['details'] ?? null),
            self::boolean($row['is_active'] ?? null, 'is_active'),
            self::date($row['created_at'] ?? null, 'created_at'),
        );
    }

    public static function string(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('The persisted ' . $field . ' value must be a string.');
        }

        return $value;
    }

    public static function integer(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException('The persisted ' . $field . ' value must be a non-negative integer.');
    }

    private static function nullablePositiveInteger(mixed $value, string $field): int|null
    {
        if ($value === null) {
            return null;
        }

        $integer = self::integer($value, $field);
        if ($integer < 1) {
            throw new UnexpectedValueException('The persisted ' . $field . ' value must be positive.');
        }

        return $integer;
    }

    private static function boolean(mixed $value, string $field): bool
    {
        $integer = self::integer($value, $field);
        if ($integer !== 0 && $integer !== 1) {
            throw new UnexpectedValueException('The persisted ' . $field . ' value must be zero or one.');
        }

        return $integer === 1;
    }

    /** @return array<string, mixed> */
    private static function jsonObject(mixed $value): array
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('The persisted details value must be JSON.');
        }

        try {
            $details = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new UnexpectedValueException('The persisted details value is invalid.', 0, $exception);
        }

        if (! is_array($details)) {
            throw new UnexpectedValueException('The persisted details value must be an object.');
        }

        foreach (array_keys($details) as $key) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('The persisted details value must be an object.');
            }
        }

        /** @var array<string, mixed> $jsonObject */
        $jsonObject = $details;

        return $jsonObject;
    }

    private static function date(mixed $value, string $field): DateTimeImmutable
    {
        try {
            return DateTimeImmutableFactory::create(self::string($value, $field));
        } catch (Throwable $exception) {
            throw new UnexpectedValueException('The persisted ' . $field . ' value is invalid.', 0, $exception);
        }
    }
}
