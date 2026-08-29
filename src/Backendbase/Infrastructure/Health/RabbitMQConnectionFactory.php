<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use PhpAmqpLib\Connection\AbstractConnection;

interface RabbitMQConnectionFactory
{
    public function create(): AbstractConnection;
}
