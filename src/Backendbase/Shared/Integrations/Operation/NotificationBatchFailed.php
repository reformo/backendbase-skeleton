<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

use RuntimeException;
use Throwable;

final class NotificationBatchFailed extends RuntimeException
{
    public function __construct(
        private readonly NotificationResult $completed,
        private readonly int $failedIndex,
        Throwable $previous,
    ) {
        parent::__construct('Notification batch failed after partial delivery.', 0, $previous);
    }

    public function completed(): NotificationResult
    {
        return $this->completed;
    }

    public function failedIndex(): int
    {
        return $this->failedIndex;
    }
}
