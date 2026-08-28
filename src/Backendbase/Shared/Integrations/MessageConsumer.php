<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;

interface MessageConsumer
{
    /** @param callable(Message): QueueMessageHandlingOutcome $handler */
    public function consume(MessageSubscription $subscription, callable $handler): void;
}
