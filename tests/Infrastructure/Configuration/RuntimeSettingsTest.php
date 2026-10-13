<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Shared\Options\System\Environment;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class RuntimeSettingsTest extends TestCase
{
    #[Test]
    public function itProvidesApplicationRuntimeValues(): void
    {
        $settings = new ApplicationRuntimeSettings(new Settings([
            'env' => 'test',
            'base-path' => '/api',
            'cdnBaseUrl' => 'https://cdn.example.com/',
            'displayErrorDetails' => true,
            'logError' => false,
            'logErrorDetails' => false,
            'route-cache-file' => 'var/cache/routes.php',
        ]));

        self::assertSame(Environment::TEST, $settings->environment());
        self::assertSame('/api', $settings->basePath());
        self::assertSame('https://cdn.example.com/', $settings->cdnBaseUrl());
        self::assertTrue($settings->displaysErrorDetails());
        self::assertFalse($settings->logsErrors());
        self::assertFalse($settings->logsErrorDetails());
        self::assertSame('var/cache/routes.php', $settings->routeCacheFile());
    }

    #[Test]
    public function itRejectsInvalidApplicationRuntimeValues(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The application runtime settings are invalid.');

        new ApplicationRuntimeSettings(new Settings(['base-path' => 42]));
    }
}
