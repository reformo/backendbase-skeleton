<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use UnexpectedValueException;

use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * @phpstan-type QueueSettings array{driver: 'rabbitmq'|'sqs'}
 * @phpstan-type RabbitMQConnectionSettings array{
 *     host: string,
 *     port: int,
 *     user: string,
 *     password: string,
 *     vhost: string
 * }
 * @phpstan-type RabbitMQRuntimeConnectionSettings array{
 *     host: string,
 *     port: int,
 *     user: string,
 *     password: string,
 *     vhost: string,
 *     'connection-timeout': float,
 *     'read-write-timeout': float,
 *     heartbeat: int
 * }
 * @phpstan-type RabbitMQTopologySettings array{
 *     queue: string,
 *     exchange: string,
 *     'exchange-type': string,
 *     'dead-letter-exchange': string,
 *     'dead-letter-queue-suffix': string,
 *     'message-retention-milliseconds': int,
 *     'prefetch-count': int
 * }
 */
final class ValidatedQueueSettings
{
    /** @return QueueSettings */
    public static function queue(mixed $settings): array
    {
        $driver = is_array($settings) ? ($settings['driver'] ?? null) : null;
        if ($driver !== 'rabbitmq' && $driver !== 'sqs') {
            throw new UnexpectedValueException('The queue driver must be rabbitmq or sqs.');
        }

        return ['driver' => $driver];
    }

    /** @return RabbitMQConnectionSettings */
    public static function rabbitMQConnection(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || ! is_string($settings['host'] ?? null)
            || ! is_int($settings['port'] ?? null)
            || ! is_string($settings['user'] ?? null)
            || ! is_string($settings['password'] ?? null)
            || ! is_string($settings['vhost'] ?? null)
        ) {
            throw new UnexpectedValueException('The RabbitMQ connection settings are invalid.');
        }

        return [
            'host' => $settings['host'],
            'port' => $settings['port'],
            'user' => $settings['user'],
            'password' => $settings['password'],
            'vhost' => $settings['vhost'],
        ];
    }

    /** @return RabbitMQRuntimeConnectionSettings */
    public static function rabbitMQRuntimeConnection(mixed $settings): array
    {
        $connection = self::rabbitMQConnection($settings);
        if (
            ! is_array($settings)
            || (! is_float($settings['connection-timeout'] ?? null) && ! is_int($settings['connection-timeout'] ?? null))
            || (! is_float($settings['read-write-timeout'] ?? null) && ! is_int($settings['read-write-timeout'] ?? null))
            || ! is_int($settings['heartbeat'] ?? null)
        ) {
            throw new UnexpectedValueException('The RabbitMQ runtime connection settings are invalid.');
        }

        return [
            ...$connection,
            'connection-timeout' => (float) $settings['connection-timeout'],
            'read-write-timeout' => (float) $settings['read-write-timeout'],
            'heartbeat' => $settings['heartbeat'],
        ];
    }

    /** @return RabbitMQTopologySettings */
    public static function rabbitMQTopology(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || ! is_string($settings['queue'] ?? null)
            || ! is_string($settings['exchange'] ?? null)
            || ! is_string($settings['exchange-type'] ?? null)
            || ! is_string($settings['dead-letter-exchange'] ?? null)
            || ! is_string($settings['dead-letter-queue-suffix'] ?? null)
            || ! is_int($settings['message-retention-milliseconds'] ?? null)
            || ! is_int($settings['prefetch-count'] ?? null)
        ) {
            throw new UnexpectedValueException('The RabbitMQ topology settings are invalid.');
        }

        return [
            'queue' => $settings['queue'],
            'exchange' => $settings['exchange'],
            'exchange-type' => $settings['exchange-type'],
            'dead-letter-exchange' => $settings['dead-letter-exchange'],
            'dead-letter-queue-suffix' => $settings['dead-letter-queue-suffix'],
            'message-retention-milliseconds' => $settings['message-retention-milliseconds'],
            'prefetch-count' => $settings['prefetch-count'],
        ];
    }
}
