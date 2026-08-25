<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;

final class RabbitMQConnection
{
    private AMQPChannel|null $channel = null;

    public function __construct(private readonly AbstractConnection $connection)
    {
    }

    public function channel(): AMQPChannel
    {
        if ($this->channel === null || ! $this->channel->is_open()) {
            $this->channel = $this->connection->channel();
            $this->channel->confirm_select();
        }

        return $this->channel;
    }

    public function close(): void
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            $this->channel->close();
        }

        if (! $this->connection->isConnected()) {
            return;
        }

        $this->connection->close();
    }
}
