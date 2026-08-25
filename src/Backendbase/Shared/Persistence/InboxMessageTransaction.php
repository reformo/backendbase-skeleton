<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

interface InboxMessageTransaction
{
    /**
     * Run the database mutation and inbox write in one transaction.
     *
     * The mutation must not perform network, process, or filesystem input and output.
     *
     * @param callable(): void $databaseMutation
     */
    public function processOnce(
        string $consumerName,
        string $messageId,
        string $eventName,
        callable $databaseMutation,
    ): void;
}
