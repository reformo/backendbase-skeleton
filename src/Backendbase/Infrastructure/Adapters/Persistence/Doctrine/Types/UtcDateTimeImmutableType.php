<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Types;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

use function is_string;
use function str_contains;

final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform instanceof AbstractMySQLPlatform) {
            return 'DATETIME(6)';
        }

        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof DateTimeImmutable) {
            throw InvalidType::new($value, self::class, [DateTimeImmutable::class, 'null']);
        }

        $utc = $value->setTimezone(new DateTimeZone('UTC'));

        return $utc->format('Y-m-d H:i:s.u');
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): DateTimeImmutable|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(new DateTimeZone('UTC'));
        }

        if (! is_string($value)) {
            throw InvalidType::new($value, self::class, ['string', 'null', DateTimeImmutable::class]);
        }

        $format = str_contains($value, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
        $date   = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format($format) !== $value) {
            throw InvalidFormat::new($value, self::class, $format);
        }

        return $date;
    }
}
