<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Exception\InvalidType;

final class UtcDateTimeMutableType extends DateTimeType
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $type = new UtcDateTimeImmutableType();

        return $type->getSQLDeclaration($column, $platform);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        if ($value !== null && ! $value instanceof DateTimeInterface) {
            throw InvalidType::new($value, self::class, [DateTimeInterface::class, 'null']);
        }

        $immutable = $value === null ? null : DateTimeImmutable::createFromInterface($value);
        $type      = new UtcDateTimeImmutableType();

        return $type->convertToDatabaseValue($immutable, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): DateTime|null
    {
        $immutable = $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : $value;
        $type      = new UtcDateTimeImmutableType();
        $date      = $type->convertToPHPValue($immutable, $platform);

        return $date === null ? null : DateTime::createFromImmutable($date);
    }
}
