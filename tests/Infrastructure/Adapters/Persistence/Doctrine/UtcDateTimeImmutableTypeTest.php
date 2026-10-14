<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Types\UtcDateTimeImmutableType;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class UtcDateTimeImmutableTypeTest extends TestCase
{
    public function testUtcRoundTripPreservesMicrosecondsIndependentlyOfServerTimezone(): void
    {
        $type     = new UtcDateTimeImmutableType();
        $platform = new MySQL84Platform();
        $input    = new DateTimeImmutable('2026-10-14T12:34:56.123456+03:00');
        self::assertSame('DATETIME(6)', $type->getSQLDeclaration([], $platform));
        self::assertSame('DATETIME', $type->getSQLDeclaration([], new SQLitePlatform()));
        self::assertSame('2026-10-14 09:34:56.123456', $type->convertToDatabaseValue($input, $platform));
        $loaded = $type->convertToPHPValue('2026-10-14 09:34:56.123456', $platform);
        self::assertSame('2026-10-14T09:34:56.123456+00:00', $loaded->format('Y-m-d\TH:i:s.uP'));
        $converted = $type->convertToPHPValue($input, $platform);
        $timezone  = $converted->getTimezone();
        self::assertSame('UTC', $timezone->getName());
        $seconds = $type->convertToPHPValue('2026-10-14 09:34:56', $platform);
        self::assertSame('2026-10-14 09:34:56', $seconds->format('Y-m-d H:i:s'));
        self::assertSame(null, $type->convertToPHPValue(null, $platform));
        self::assertSame(null, $type->convertToDatabaseValue(null, $platform));
    }

    /** @param class-string<Throwable> $exception */
    #[DataProvider('invalidDates')]
    public function testStoredDatesRejectInvalidTypesAndNonCanonicalFormats(mixed $input, string $exception): void
    {
        $this->expectException($exception);
        $type = new UtcDateTimeImmutableType();
        $type->convertToPHPValue($input, new MySQL84Platform());
    }

    /** @return iterable<string, array{mixed, class-string<Throwable>}> */
    public static function invalidDates(): iterable
    {
        yield 'integer' => [123, InvalidType::class];
        yield 'mutable date' => [new DateTime(), InvalidType::class];
        yield 'calendar overflow' => ['2026-02-30 00:00:00', InvalidFormat::class];
        yield 'invalid syntax' => ['invalid', InvalidFormat::class];
        yield 'short fractional part' => ['2026-10-14 10:00:00.1', InvalidFormat::class];
    }

    public function testMutableDatesCannotEnterPersistence(): void
    {
        $this->expectException(InvalidType::class);
        new UtcDateTimeImmutableType()->convertToDatabaseValue(new DateTime(), new SQLitePlatform());
    }
}
