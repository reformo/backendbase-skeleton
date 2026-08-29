<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\Queue\QueueDriver;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueSettingsTest extends TestCase
{
    #[Test]
    public function itProvidesValidatedQueueValues(): void
    {
        $settings = new QueueSettings(new Settings([
            'queue' => ['driver' => 'rabbitmq'],
            'rabbitmq' => [
                'host' => '127.0.0.1',
                'port' => 5672,
                'user' => 'backendbase',
                'password' => 'backendbase',
                'vhost' => '/',
                'connectionTimeout' => 3,
                'readWriteTimeout' => 35,
                'heartbeat' => 15,
                'queue' => 'backendbase-queue',
                'exchange' => 'backendbase',
                'exchangeType' => 'direct',
                'deadLetterExchange' => 'backendbase.dead-letter',
                'deadLetterQueueSuffix' => '.dead-letter',
                'messageRetentionMilliseconds' => 604800000,
                'prefetchCount' => 1,
            ],
            'readiness' => ['timeoutSeconds' => 2],
        ]));

        self::assertSame(QueueDriver::RABBIT_MQ, $settings->driver());
        $connection = $settings->rabbitMqConnection();
        self::assertSame('127.0.0.1', $connection->host());
        self::assertSame(5672, $connection->port());
        self::assertSame('backendbase', $connection->user());
        self::assertSame('backendbase', $connection->password());
        self::assertSame('/', $connection->virtualHost());
        $runtime = $settings->rabbitMqRuntimeConnection();
        self::assertSame($connection, $runtime->connection());
        self::assertSame(3.0, $runtime->connectionTimeoutSeconds());
        self::assertSame(35.0, $runtime->readWriteTimeoutSeconds());
        self::assertSame(15, $runtime->heartbeatSeconds());
        $topology = $settings->rabbitMqTopology();
        self::assertSame('backendbase-queue', $topology->queueName());
        self::assertSame('backendbase', $topology->exchangeName());
        self::assertSame('direct', $topology->exchangeType());
        self::assertSame('backendbase.dead-letter', $topology->deadLetterExchangeName());
        self::assertSame('.dead-letter', $topology->deadLetterQueueSuffix());
        self::assertSame(604800000, $topology->messageRetentionMilliseconds());
        self::assertSame(1, $topology->prefetchCount());
        self::assertSame(2.0, $settings->readinessTimeoutSeconds());
    }
}
