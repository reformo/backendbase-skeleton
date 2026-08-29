<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use Backendbase\Shared\Health\ReadinessCheck;
use Override;
use UnexpectedValueException;

final readonly class RabbitMQReadinessCheck implements ReadinessCheck
{
    public function __construct(private RabbitMQConnectionFactory $connectionFactory)
    {
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return 'queue';
    }

    #[Override]
    public function check(): void
    {
        $connection = $this->connectionFactory->create();
        $channel    = null;

        try {
            $channel = $connection->channel();
            if (! $channel->is_open()) {
                throw new UnexpectedValueException('The RabbitMQ readiness channel did not open.');
            }
        } finally {
            if ($channel !== null && $channel->is_open()) {
                $channel->close();
            }

            if ($connection->isConnected()) {
                $connection->close();
            }
        }
    }
}
