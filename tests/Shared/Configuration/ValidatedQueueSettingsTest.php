<?php

declare(strict_types=1);

namespace Tests\Shared\Configuration;

use Backendbase\Shared\Configuration\ValidatedQueueSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ValidatedQueueSettingsTest extends TestCase
{
    #[Test]
    public function itReturnsValidatedQueueShapes(): void
    {
        $runtimeConnection                       = self::runtimeConnection();
        $runtimeConnection['connection-timeout'] = 3;

        self::assertSame(['driver' => 'rabbitmq'], ValidatedQueueSettings::queue(['driver' => 'rabbitmq']));
        self::assertSame(self::connection(), ValidatedQueueSettings::rabbitMQConnection(self::connection()));
        self::assertSame(
            self::runtimeConnection(),
            ValidatedQueueSettings::rabbitMQRuntimeConnection($runtimeConnection),
        );
        self::assertSame(self::topology(), ValidatedQueueSettings::rabbitMQTopology(self::topology()));
    }

    /** @param callable(mixed): array<string, mixed> $validator */
    #[DataProvider('invalidSettings')]
    #[Test]
    public function itRejectsInvalidQueueShapes(callable $validator, mixed $settings, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $validator($settings);
    }

    /** @return iterable<string, array{callable(mixed): array<string, mixed>, mixed, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'queue' => [
            ValidatedQueueSettings::queue(...),
            ['driver' => 'invalid'],
            'The queue driver must be rabbitmq or sqs.',
        ];

        yield 'connection' => [
            ValidatedQueueSettings::rabbitMQConnection(...),
            [],
            'The RabbitMQ connection settings are invalid.',
        ];

        yield 'runtime connection' => [
            ValidatedQueueSettings::rabbitMQRuntimeConnection(...),
            self::connection(),
            'The RabbitMQ runtime connection settings are invalid.',
        ];

        yield 'topology' => [
            ValidatedQueueSettings::rabbitMQTopology(...),
            [],
            'The RabbitMQ topology settings are invalid.',
        ];
    }

    /** @return array{host: string, port: int, user: string, password: string, vhost: string} */
    private static function connection(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 5672,
            'user' => 'backendbase',
            'password' => 'backendbase',
            'vhost' => '/',
        ];
    }

    /**
     * @return array{
     *     host: string,
     *     port: int,
     *     user: string,
     *     password: string,
     *     vhost: string,
     *     'connection-timeout': float,
     *     'read-write-timeout': float,
     *     heartbeat: int
     * }
     */
    private static function runtimeConnection(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 5672,
            'user' => 'backendbase',
            'password' => 'backendbase',
            'vhost' => '/',
            'connection-timeout' => 3.0,
            'read-write-timeout' => 35.0,
            'heartbeat' => 15,
        ];
    }

    /** @return array<string, int|string> */
    private static function topology(): array
    {
        return [
            'queue' => 'backendbase-queue',
            'exchange' => 'backendbase',
            'exchange-type' => 'direct',
            'dead-letter-exchange' => 'backendbase.dead-letter',
            'dead-letter-queue-suffix' => '.dead-letter',
            'message-retention-milliseconds' => 604800000,
            'prefetch-count' => 1,
        ];
    }
}
