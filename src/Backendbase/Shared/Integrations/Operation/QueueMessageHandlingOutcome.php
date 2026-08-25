<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

enum QueueMessageHandlingOutcome
{
    case ACKNOWLEDGE;
    case REJECT;
    case RETRY;
}
