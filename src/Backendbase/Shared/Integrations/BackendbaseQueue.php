<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;

interface BackendbaseQueue
{
    /** @param array<string, mixed> $params */
    public function publish(array $params): mixed;

    /**
     * @param array<string, mixed>                                        $params
     * @param callable(array<string, mixed>): QueueMessageHandlingOutcome $handler
     */
    public function consume(array $params, callable $handler): void;
}
