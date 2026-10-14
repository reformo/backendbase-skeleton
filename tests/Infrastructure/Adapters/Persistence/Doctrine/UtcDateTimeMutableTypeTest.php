<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Types\UtcDateTimeMutableType;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtcDateTimeMutableTypeTest extends TestCase
{
    public function testMutableFrameworkDatesKeepUtcMicrosecondsAndTheirPhpType(): void
    {
        $type     = new UtcDateTimeMutableType();
        $platform = new MySQLPlatform();
        self::assertSame('DATETIME(6)', $type->getSQLDeclaration([], $platform));
        self::assertSame('DATETIME', $type->getSQLDeclaration([], new SQLitePlatform()));
        $date = new DateTime('2026-10-14T13:00:00.123456+03:00');
        self::assertSame('2026-10-14 10:00:00.123456', $type->convertToDatabaseValue($date, $platform));
        self::assertSame('+03:00', $date->format('P'));
        $restored = $type->convertToPHPValue('2026-10-14 10:00:00.123456', $platform);
        self::assertInstanceOf(DateTime::class, $restored);
        self::assertSame('2026-10-14T10:00:00.123456+00:00', $restored->format('Y-m-d\TH:i:s.uP'));
        $restored = $type->convertToPHPValue($date, $platform);
        self::assertInstanceOf(DateTime::class, $restored);
        self::assertSame('UTC', $restored->getTimezone()->getName());
        $immutable = new DateTimeImmutable('2026-10-14T13:00:00.123456+03:00');
        self::assertSame('2026-10-14 10:00:00.123456', $type->convertToDatabaseValue($immutable, $platform));
    }

    #[DataProvider('nullInputs')]
    public function testNullValuesRemainNull(mixed $input): void
    {
        $type     = new UtcDateTimeMutableType();
        $platform = new MySQLPlatform();
        self::assertNull($type->convertToDatabaseValue($input, $platform));
        self::assertNull($type->convertToPHPValue($input, $platform));
    }

    /** @return list<array{null}> */
    public static function nullInputs(): array
    {
        return [[null]];
    }

    public function testInvalidMutableDatabaseValuesAreRejected(): void
    {
        $this->expectException(InvalidType::class);
        new UtcDateTimeMutableType()->convertToDatabaseValue('untrusted', new MySQLPlatform());
    }
}
