<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Helpers\DateTimeImmutable;

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

    public function hasPendingMessageOlderThan(int $maximumAgeSeconds): bool
    {
        if ($this->oldestPendingAt === null) {
            return false;
        }

        $oldestPending = DateTimeImmutable::create($this->oldestPendingAt);
        $cutoff        = DateTimeImmutable::create()->modify('-' . $maximumAgeSeconds . ' seconds');

        return $oldestPending < $cutoff;
    }
}
