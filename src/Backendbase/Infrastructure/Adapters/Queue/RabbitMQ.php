<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\RabbitMQConnection;
use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\RabbitMQMessageMapper;
use Backendbase\Infrastructure\Adapters\Queue\RabbitMQ\RabbitMQTopology;
use Backendbase\Shared\Integrations\BackendbaseQueue;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMQ implements BackendbaseQueue
{
    private readonly RabbitMQConnection $connection;
    private readonly RabbitMQTopology $topology;

    /** @param array<string, mixed> $settings */
    public function __construct(AbstractConnection $connection, array $settings)
    {
        $this->connection = new RabbitMQConnection($connection);
        $this->topology   = new RabbitMQTopology($settings);
    }

    public function __destruct()
    {
        $this->connection->close();
    }

    /** @param array<string, mixed> $params */
    #[Override]
    public function publish(array $params): mixed
    {
        $route   = $this->topology->route($params);
        $channel = $this->connection->channel();
        $this->topology->declare($channel, $route);

        $channel->basic_publish(
            RabbitMQMessageMapper::outboundMessage($params),
            $route['exchange'],
            $route['routingKey'],
            true,
        );
        $channel->wait_for_pending_acks_returns();

        return null;
    }

    /** @param array<string, mixed> $params */
    #[Override]
    public function consume(array $params, callable $handler): void
    {
        $route   = $this->topology->route($params);
        $channel = $this->connection->channel();
        $this->topology->declare($channel, $route);
        $channel->basic_qos(0, $this->topology->prefetchCount($params), false);
        $channel->basic_consume(
            $route['queue'],
            'backendbase-' . $route['queue'],
            false,
            false,
            false,
            false,
            function (AMQPMessage $message) use ($handler, $route): void {
                $this->handleMessage($message, $handler, $route['queue']);
            },
        );

        $this->waitForMessages($channel, $this->topology->waitTimeout($params));
    }

    private function handleMessage(AMQPMessage $message, callable $handler, string $queue): void
    {
        try {
            $outcome = $handler(RabbitMQMessageMapper::inboundPayload($message, $queue));
        } catch (Throwable) {
            $message->nack(true);

            return;
        }

        if ($outcome === QueueMessageHandlingOutcome::ACKNOWLEDGE) {
            $message->ack();

            return;
        }

        if ($outcome === QueueMessageHandlingOutcome::REJECT) {
            $message->reject(false);

            return;
        }

        $message->nack(true);
    }

    private function waitForMessages(AMQPChannel $channel, float $waitTimeout): void
    {
        while ($channel->is_consuming()) {
            $this->wait($channel, $waitTimeout);
        }
    }

    private function wait(AMQPChannel $channel, float $waitTimeout): void
    {
        try {
            if ($waitTimeout > 0) {
                $channel->wait(null, false, $waitTimeout);

                return;
            }

            $channel->wait();
        } catch (AMQPTimeoutException) {
            return;
        }
    }
}
