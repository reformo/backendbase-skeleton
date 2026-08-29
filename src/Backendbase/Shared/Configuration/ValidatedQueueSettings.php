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
 *     connectionTimeout: float,
 *     readWriteTimeout: float,
 *     heartbeat: int
 * }
 * @phpstan-type RabbitMQTopologySettings array{
 *     queue: string,
 *     exchange: string,
 *     exchangeType: string,
 *     deadLetterExchange: string,
 *     deadLetterQueueSuffix: string,
 *     messageRetentionMilliseconds: int,
 *     prefetchCount: int
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
            || (! is_float($settings['connectionTimeout'] ?? null) && ! is_int($settings['connectionTimeout'] ?? null))
            || (! is_float($settings['readWriteTimeout'] ?? null) && ! is_int($settings['readWriteTimeout'] ?? null))
            || ! is_int($settings['heartbeat'] ?? null)
        ) {
            throw new UnexpectedValueException('The RabbitMQ runtime connection settings are invalid.');
        }

        return [
            ...$connection,
            'connectionTimeout' => (float) $settings['connectionTimeout'],
            'readWriteTimeout' => (float) $settings['readWriteTimeout'],
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
            || ! is_string($settings['exchangeType'] ?? null)
            || ! is_string($settings['deadLetterExchange'] ?? null)
            || ! is_string($settings['deadLetterQueueSuffix'] ?? null)
            || ! is_int($settings['messageRetentionMilliseconds'] ?? null)
            || ! is_int($settings['prefetchCount'] ?? null)
        ) {
            throw new UnexpectedValueException('The RabbitMQ topology settings are invalid.');
        }

        return [
            'queue' => $settings['queue'],
            'exchange' => $settings['exchange'],
            'exchangeType' => $settings['exchangeType'],
            'deadLetterExchange' => $settings['deadLetterExchange'],
            'deadLetterQueueSuffix' => $settings['deadLetterQueueSuffix'],
            'messageRetentionMilliseconds' => $settings['messageRetentionMilliseconds'],
            'prefetchCount' => $settings['prefetchCount'],
        ];
    }
}
