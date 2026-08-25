<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMQTopology
{
    /** @param array<string, mixed> $settings */
    public function __construct(private readonly array $settings)
    {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{queue: string, exchange: string, routingKey: string}
     */
    public function route(array $params): array
    {
        $queue = (string) (
            $params['queueName']
            ?? $params['queue']
            ?? $params['topic']
            ?? $this->settings['queue']
        );

        return [
            'queue' => $queue,
            'exchange' => (string) ($params['exchange'] ?? $this->settings['exchange']),
            'routingKey' => (string) ($params['routingKey'] ?? $params['tag'] ?? $queue),
        ];
    }

    /** @param array<string, mixed> $params */
    public function prefetchCount(array $params): int
    {
        return (int) ($params['maxNumberOfMessages'] ?? $this->settings['prefetchCount']);
    }

    /** @param array<string, mixed> $params */
    public function waitTimeout(array $params): float
    {
        return (float) ($params['waitTimeSeconds'] ?? 0);
    }

    /** @param array{queue: string, exchange: string, routingKey: string} $route */
    public function declare(AMQPChannel $channel, array $route): void
    {
        $deadLetterExchange = (string) $this->settings['deadLetterExchange'];
        $deadLetterQueue    = $route['queue'] . (string) $this->settings['deadLetterQueueSuffix'];
        $channel->exchange_declare($deadLetterExchange, 'direct', false, true, false);
        $channel->queue_declare($deadLetterQueue, false, true, false, false);
        $channel->queue_bind($deadLetterQueue, $deadLetterExchange, $route['routingKey']);
        $channel->queue_declare(
            $route['queue'],
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $deadLetterExchange,
                'x-dead-letter-routing-key' => $route['routingKey'],
                'x-message-ttl' => (int) $this->settings['messageRetentionMilliseconds'],
            ]),
        );

        if ($route['exchange'] === '') {
            return;
        }

        $channel->exchange_declare(
            $route['exchange'],
            (string) $this->settings['exchangeType'],
            false,
            true,
            false,
        );
        $channel->queue_bind($route['queue'], $route['exchange'], $route['routingKey']);
    }
}
