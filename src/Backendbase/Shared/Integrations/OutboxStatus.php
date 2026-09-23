<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use DateTimeImmutable;

final readonly class OutboxStatus
{
    public function __construct(
        private int $pendingMessages,
        private int $retriedMessages,
        private string|null $oldestPendingAt,
    ) {
    }

    public function pendingMessages(): int
    {
        return $this->pendingMessages;
    }

    public function retriedMessages(): int
    {
        return $this->retriedMessages;
    }

    public function oldestPendingAt(): string|null
    {
        return $this->oldestPendingAt;
    }

    public function hasPendingMessageOlderThan(int $maximumAgeSeconds, DateTimeImmutable $now): bool
    {
        if ($this->oldestPendingAt === null) {
            return false;
        }

        $oldestPending = DateTimeImmutableFactory::create($this->oldestPendingAt);
        $cutoff        = $now->modify('-' . $maximumAgeSeconds . ' seconds');

        return $oldestPending < $cutoff;
    }
}
