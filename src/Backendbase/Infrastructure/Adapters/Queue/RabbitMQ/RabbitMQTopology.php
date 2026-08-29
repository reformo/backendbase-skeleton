<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use Backendbase\Infrastructure\Configuration\Queue\RabbitMQTopologySettings;
use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMQTopology
{
    public function __construct(private readonly RabbitMQTopologySettings $settings)
    {
    }

    /** @return array{queue: string, exchange: string, routingKey: string} */
    public function publicationRoute(Message $message): array
    {
        $queue      = $message->destination();
        $queue    ??= $this->settings->queueName();
        $exchange   = $this->settings->exchangeName();
        $routingKey = $message->routingKey() ?? $queue;

        return [
            'queue' => $queue,
            'exchange' => $exchange,
            'routingKey' => $routingKey,
        ];
    }

    /** @return array{queue: string, exchange: string, routingKey: string} */
    public function subscriptionRoute(MessageSubscription $subscription): array
    {
        $destination = $subscription->destination();
        $exchange    = $this->settings->exchangeName();

        return [
            'queue' => $destination,
            'exchange' => $exchange,
            'routingKey' => $destination,
        ];
    }

    public function prefetchCount(MessageSubscription $subscription): int
    {
        $prefetchCount = $subscription->maxNumberOfMessages();

        return $prefetchCount ?? $this->settings->prefetchCount();
    }

    public function waitTimeout(MessageSubscription $subscription): float
    {
        return $subscription->waitTimeSeconds() ?? 0;
    }

    /** @param array{queue: string, exchange: string, routingKey: string} $route */
    public function declare(AMQPChannel $channel, array $route): void
    {
        $deadLetterExchange = $this->settings->deadLetterExchangeName();
        $deadLetterQueue    = $route['queue'] . $this->settings->deadLetterQueueSuffix();
        $messageRetention   = $this->settings->messageRetentionMilliseconds();
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
                'x-message-ttl' => $messageRetention,
            ]),
        );

        if ($route['exchange'] === '') {
            return;
        }

        $exchangeType = $this->settings->exchangeType();
        $channel->exchange_declare(
            $route['exchange'],
            $exchangeType,
            false,
            true,
            false,
        );
        $channel->queue_bind($route['queue'], $route['exchange'], $route['routingKey']);
    }
}
