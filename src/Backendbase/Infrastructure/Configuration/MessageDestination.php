<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\Queue\QueueDriver;
use InvalidArgumentException;

final readonly class MessageDestination
{
    private string $name;

    public function __construct(QueueSettings $queueSettings, AwsSettings $awsSettings)
    {
        $topology = $queueSettings->rabbitMqTopology();
        $name     = $topology->queueName();
        if ($queueSettings->driver() === QueueDriver::SQS) {
            $sqs  = $awsSettings->sqs();
            $name = $sqs->queueName();
        }

        if ($name === null || $name === '') {
            throw new InvalidArgumentException('The queue must declare a stable consumer name.');
        }

        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
