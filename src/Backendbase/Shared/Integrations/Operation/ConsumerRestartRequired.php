<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

use RuntimeException;
use Throwable;

final class ConsumerRestartRequired extends RuntimeException
{
    public function __construct(
        Throwable $previous,
        private readonly QueueMessageHandlingOutcome $outcome = QueueMessageHandlingOutcome::RETRY,
    ) {
        parent::__construct('The queue consumer requires a fresh persistence session.', 0, $previous);
    }

    public function outcome(): QueueMessageHandlingOutcome
    {
        return $this->outcome;
    }
}
