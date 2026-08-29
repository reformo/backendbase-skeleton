<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Queue;

enum QueueDriver: string
{
    case RABBIT_MQ = 'rabbitmq';
    case SQS       = 'sqs';
}
