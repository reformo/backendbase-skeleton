<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Shared\Configuration\ApiKeySettings;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class HttpSettingsTest extends TestCase
{
    #[Test]
    public function itRejectsAnEmptyAllowedOriginList(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('At least one HTTP origin must be configured.');

        new HttpHeaderSettings(new Settings([
            'headers' => [
                'Access-Control-Allow-Origin' => ' , ',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ],
        ]));
    }

    #[Test]
    public function itIndexesOnlyApiKeySections(): void
    {
        $settings = new ApiKeySettings(new Settings([
            'env' => 'test',
            'service' => [],
            'example-api' => ['api-key' => 'expected-key'],
            'public-api' => ['api-key' => null],
        ]));

        self::assertTrue($settings->accepts('example-api', 'expected-key'));
        self::assertFalse($settings->accepts('example-api', 'wrong-key'));
        self::assertFalse($settings->accepts('public-api', 'candidate'));
        self::assertFalse($settings->accepts('missing-api', 'candidate'));
    }

    #[Test]
    public function itRejectsAnInvalidApplicationSettingsCollection(): void
    {
        $settings = $this->createStub(SettingsInterface::class);
        $settings->method('get')->willReturn('invalid');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The application settings are invalid.');

        new ApiKeySettings($settings);
    }
}
