<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Queue;

use Backendbase\Shared\Configuration\ValidatedQueueSettings;

/** @phpstan-import-type RabbitMQTopologySettings from ValidatedQueueSettings as RabbitMQTopologyValues */
final readonly class RabbitMQTopologySettings
{
    /** @param RabbitMQTopologyValues $values */
    public function __construct(private array $values)
    {
    }

    public function queueName(): string
    {
        return $this->values['queue'];
    }

    public function exchangeName(): string
    {
        return $this->values['exchange'];
    }

    public function exchangeType(): string
    {
        return $this->values['exchangeType'];
    }

    public function deadLetterExchangeName(): string
    {
        return $this->values['deadLetterExchange'];
    }

    public function deadLetterQueueSuffix(): string
    {
        return $this->values['deadLetterQueueSuffix'];
    }

    public function messageRetentionMilliseconds(): int
    {
        return $this->values['messageRetentionMilliseconds'];
    }

    public function prefetchCount(): int
    {
        return $this->values['prefetchCount'];
    }
}
