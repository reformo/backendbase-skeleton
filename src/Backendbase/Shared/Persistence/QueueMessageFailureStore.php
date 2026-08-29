<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use DateTimeImmutable;

interface QueueMessageFailureStore
{
    public function recordFailure(
        string $consumerName,
        string $messageId,
        string $failureType,
        DateTimeImmutable $failedAt,
        bool $deadLettered,
    ): int;

    public function markDeadLettered(
        string $consumerName,
        string $messageId,
        DateTimeImmutable $deadLetteredAt,
    ): void;

    public function clear(string $consumerName, string $messageId): void;
}
