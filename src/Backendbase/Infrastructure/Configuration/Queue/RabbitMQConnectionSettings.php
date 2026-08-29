<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Queue;

use Backendbase\Shared\Configuration\ValidatedQueueSettings;

/** @phpstan-import-type RabbitMQConnectionSettings from ValidatedQueueSettings as RabbitMQConnectionValues */
final readonly class RabbitMQConnectionSettings
{
    /** @param RabbitMQConnectionValues $values */
    public function __construct(private array $values)
    {
    }

    public function host(): string
    {
        return $this->values['host'];
    }

    public function port(): int
    {
        return $this->values['port'];
    }

    public function user(): string
    {
        return $this->values['user'];
    }

    public function password(): string
    {
        return $this->values['password'];
    }

    public function virtualHost(): string
    {
        return $this->values['vhost'];
    }
}
