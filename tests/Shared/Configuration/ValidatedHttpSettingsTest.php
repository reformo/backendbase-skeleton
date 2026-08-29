<?php

declare(strict_types=1);

namespace Tests\Shared\Configuration;

use Backendbase\Shared\Configuration\ValidatedHttpSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ValidatedHttpSettingsTest extends TestCase
{
    #[Test]
    public function itReturnsValidatedHttpShapes(): void
    {
        $headers = [
            'Access-Control-Allow-Origin' => 'https://example.com',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ];

        self::assertSame($headers, ValidatedHttpSettings::headers($headers));
        self::assertSame(['api-key' => 'secret'], ValidatedHttpSettings::api(['api-key' => 'secret']));
        self::assertSame(['api-key' => null], ValidatedHttpSettings::api([]));
    }

    #[DataProvider('invalidSettings')]
    #[Test]
    public function itRejectsInvalidHttpShapes(callable $validator, mixed $settings, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $validator($settings);
    }

    /** @return iterable<string, array{callable(mixed): array<string, mixed>, mixed, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'headers' => [
            ValidatedHttpSettings::headers(...),
            [],
            'The HTTP header settings are invalid.',
        ];

        yield 'API collection' => [
            ValidatedHttpSettings::api(...),
            'invalid',
            'The API settings are invalid.',
        ];

        yield 'API key' => [
            ValidatedHttpSettings::api(...),
            ['api-key' => 1],
            'The API settings are invalid.',
        ];
    }
}
