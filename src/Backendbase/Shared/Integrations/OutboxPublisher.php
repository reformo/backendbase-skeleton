<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\OutboxPublicationResult;
use Backendbase\Shared\Persistence\ClaimedOutboxMessage;

interface OutboxPublisher
{
    public function publish(ClaimedOutboxMessage $message): OutboxPublicationResult;
}
