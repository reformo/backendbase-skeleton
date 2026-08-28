<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMQTopology
{
    /** @param array<string, mixed> $settings */
    public function __construct(private readonly array $settings)
    {
    }

    /** @return array{queue: string, exchange: string, routingKey: string} */
    public function publicationRoute(Message $message): array
    {
        $queue = $message->destination() ?? (string) $this->settings['queue'];

        return [
            'queue' => $queue,
            'exchange' => (string) $this->settings['exchange'],
            'routingKey' => $message->routingKey() ?? $queue,
        ];
    }

    /** @return array{queue: string, exchange: string, routingKey: string} */
    public function subscriptionRoute(MessageSubscription $subscription): array
    {
        return [
            'queue' => $subscription->destination(),
            'exchange' => (string) $this->settings['exchange'],
            'routingKey' => $subscription->destination(),
        ];
    }

    public function prefetchCount(MessageSubscription $subscription): int
    {
        return $subscription->maxNumberOfMessages() ?? (int) $this->settings['prefetchCount'];
    }

    public function waitTimeout(MessageSubscription $subscription): float
    {
        return $subscription->waitTimeSeconds() ?? 0;
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
