<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue\RabbitMQ;

use Backendbase\Infrastructure\Health\RabbitMQConnectionFactory;
use Override;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;

final readonly class PhpAmqpLibRabbitMQConnectionFactory implements RabbitMQConnectionFactory
{
    public function __construct(private AMQPConnectionConfig $configuration)
    {
    }

    #[Override]
    public function create(): AbstractConnection
    {
        return AMQPConnectionFactory::create($this->configuration);
    }
}
