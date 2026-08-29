<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\DatabaseSettings;
use Backendbase\Infrastructure\Configuration\LoggingSettings;
use Backendbase\Infrastructure\Configuration\RedisSettings;
use Backendbase\Shared\Services\Settings;
use Monolog\Level;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ApplicationSettingsTest extends TestCase
{
    #[Test]
    public function itProvidesDatabaseAndRedisValues(): void
    {
        $settings = new Settings([
            'doctrine' => ['connect' => 'mysql://database'],
            'readiness' => ['timeoutSeconds' => 2.5],
            'redis' => ['host' => '127.0.0.1', 'port' => 6379],
        ]);
        $database = new DatabaseSettings($settings);
        $redis    = new RedisSettings($settings);

        self::assertSame('mysql://database', $database->dsn());
        self::assertSame(3, $database->connectionTimeoutSeconds());
        self::assertSame('127.0.0.1', $redis->host());
        self::assertSame(6379, $redis->port());
        self::assertSame(2.5, $redis->readinessTimeoutSeconds());
    }

    #[Test]
    public function itProvidesLoggingValues(): void
    {
        $settings = new LoggingSettings(new Settings([
            'logger' => ['name' => 'app', 'path' => 'php://stdout', 'level' => Level::Info],
        ]));

        self::assertSame('app', $settings->name());
        self::assertSame('php://stdout', $settings->path());
        self::assertSame(Level::Info, $settings->level());
    }

    #[Test]
    public function itRejectsInvalidLoggingValues(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The logger settings are invalid.');

        new LoggingSettings(new Settings(['logger' => ['level' => 'info']]));
    }
}
