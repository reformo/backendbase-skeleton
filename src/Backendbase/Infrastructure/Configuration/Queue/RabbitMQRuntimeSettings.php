<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Queue;

use Backendbase\Shared\Configuration\ValidatedQueueSettings;

/** @phpstan-import-type RabbitMQRuntimeConnectionSettings from ValidatedQueueSettings as RabbitMQRuntimeValues */
final readonly class RabbitMQRuntimeSettings
{
    /** @param RabbitMQRuntimeValues $values */
    public function __construct(private RabbitMQConnectionSettings $connection, private array $values)
    {
    }

    public function connection(): RabbitMQConnectionSettings
    {
        return $this->connection;
    }

    public function connectionTimeoutSeconds(): float
    {
        return $this->values['connectionTimeout'];
    }

    public function readWriteTimeoutSeconds(): float
    {
        return $this->values['readWriteTimeout'];
    }

    public function heartbeatSeconds(): int
    {
        return $this->values['heartbeat'];
    }
}
