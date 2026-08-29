<?php

declare(strict_types=1);

namespace Tests\Shared\Configuration;

use Backendbase\Shared\Configuration\ValidatedApplicationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ValidatedApplicationSettingsTest extends TestCase
{
    #[Test]
    public function itReturnsValidatedApplicationShapes(): void
    {
        self::assertSame(
            ['connect' => 'mysql://database'],
            ValidatedApplicationSettings::database(['connect' => 'mysql://database']),
        );
        self::assertSame(
            ['timeoutSeconds' => 2.0],
            ValidatedApplicationSettings::readiness(['timeoutSeconds' => 2]),
        );
        self::assertSame(
            ['host' => '127.0.0.1', 'port' => 6379],
            ValidatedApplicationSettings::redis(['host' => '127.0.0.1', 'port' => 6379]),
        );
        self::assertSame(self::jwt(), ValidatedApplicationSettings::jwt(self::jwt()));
    }

    /** @param callable(mixed): array<string, mixed> $validator */
    #[DataProvider('invalidSettings')]
    #[Test]
    public function itRejectsInvalidApplicationShapes(
        callable $validator,
        mixed $settings,
        string $message,
    ): void {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $validator($settings);
    }

    /** @return iterable<string, array{callable(mixed): array<string, mixed>, mixed, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'database' => [
            ValidatedApplicationSettings::database(...),
            [],
            'The database settings are invalid.',
        ];

        yield 'readiness' => [
            ValidatedApplicationSettings::readiness(...),
            [],
            'The readiness settings are invalid.',
        ];

        yield 'Redis' => [
            ValidatedApplicationSettings::redis(...),
            ['host' => '127.0.0.1', 'port' => '6379'],
            'The Redis settings are invalid.',
        ];

        yield 'JWT' => [
            ValidatedApplicationSettings::jwt(...),
            [...self::jwt(), 'sign-key' => ''],
            'The JWT settings are invalid.',
        ];
    }

    /** @return array<string, string> */
    private static function jwt(): array
    {
        return [
            'alias' => 'USER',
            'issuer' => 'backendbase-api',
            'permitted-for' => 'example-api',
            'sign-key' => 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            'duration' => 'PT24H',
        ];
    }
}
