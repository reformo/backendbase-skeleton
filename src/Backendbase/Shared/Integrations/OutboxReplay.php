<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Persistence\RecoveryEvidence;

interface OutboxReplay
{
    public function schedule(string $messageId, RecoveryEvidence $evidence, bool $apply = false): void;
}
