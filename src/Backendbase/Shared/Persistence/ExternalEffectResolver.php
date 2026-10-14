<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

interface ExternalEffectResolver
{
    public function resolve(
        string $consumerName,
        string $messageId,
        ExternalEffectResolution $resolution,
        RecoveryEvidence $evidence,
        bool $apply = false,
    ): void;
}
