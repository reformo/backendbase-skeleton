<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Backendbase\Infrastructure\Configuration\LoggingSettings;
use Backendbase\Shared\Services\Settings;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function rewind;
use function stream_get_contents;

final class LoggerConfigurationTest extends TestCase
{
    #[Test]
    public function itWritesUtcLogRecordsWithANonUtcProcessTimezone(): void
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');

        try {
            $builder  = new ContainerBuilder();
            $provider = require 'config/dependencies/logger.php';
            $provider($builder);
            $builder->addDefinitions([
                LoggingSettings::class => new LoggingSettings(new Settings([
                    'logger' => ['name' => 'utc-test', 'path' => 'php://memory', 'level' => Level::Info],
                ])),
            ]);
            $container = $builder->build();
            $logger    = $container->get(LoggerInterface::class);
            self::assertInstanceOf(Logger::class, $logger);

            $logger->info('UTC logging check');

            $handlers = $logger->getHandlers();
            $handler  = $handlers[0];
            self::assertInstanceOf(StreamHandler::class, $handler);
            $stream = $handler->getStream();
            self::assertIsResource($stream);
            rewind($stream);
            $output = stream_get_contents($stream);
            self::assertIsString($output);
            self::assertMatchesRegularExpression('/^\[[^\]]+\+00:00\]/', $output);
            self::assertStringContainsString('utc-test.INFO: UTC logging check', $output);
            $logger->close();
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }
}
