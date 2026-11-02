<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

enum QueueMessageHandlingOutcome
{
    /** Required work completed; the transport can acknowledge the delivery. */
    case ACKNOWLEDGE;

    /** Reject the delivery according to the transport's dead-letter or redrive policy. */
    case REJECT;

    /** Keep the delivery eligible for a later attempt. */
    case RETRY;
}
