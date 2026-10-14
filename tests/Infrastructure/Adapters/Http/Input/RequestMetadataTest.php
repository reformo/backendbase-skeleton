<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Input;

use Backendbase\Infrastructure\Adapters\Http\Input\RequestMetadata;
use Backendbase\Shared\Exception\InvalidUserInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class RequestMetadataTest extends TestCase
{
    public function testMissingMetadataKeepsDocumentedDefaultsAndValidMetadataIsAccepted(): void
    {
        RequestMetadata::validate([]);
        RequestMetadata::validate(['HTTP_X_REQUEST_ID' => '019f6322-1234-7000-8000-000000000001', 'HTTP_THE_TIMEZONE_IANA' => 'Europe/Istanbul']);
        self::expectNotToPerformAssertions();
    }

    /** @param array<string, mixed> $server */
    #[DataProvider('invalidMetadata')]
    public function testInvalidHeadersFailBeforeLoggerAndContainerCreation(array $server): void
    {
        $this->expectException(InvalidUserInput::class);
        RequestMetadata::validate($server);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidMetadata(): iterable
    {
        yield 'trace type' => [['HTTP_X_REQUEST_ID' => []]];
        yield 'trace format' => [['HTTP_X_REQUEST_ID' => 'invalid-id']];
        yield 'trace syntax' => [['HTTP_X_REQUEST_ID' => str_repeat('z', 36)]];
        yield 'timezone type' => [['HTTP_THE_TIMEZONE_IANA' => []]];
        yield 'timezone missing value' => [['HTTP_THE_TIMEZONE_IANA' => '']];
        yield 'timezone size' => [['HTTP_THE_TIMEZONE_IANA' => str_repeat('x', 129)]];
        yield 'timezone name' => [['HTTP_THE_TIMEZONE_IANA' => 'Invalid/Timezone']];
        yield 'timezone offset is not an IANA name' => [['HTTP_THE_TIMEZONE_IANA' => '+03:00']];
    }
}
