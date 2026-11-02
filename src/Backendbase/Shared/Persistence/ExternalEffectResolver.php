<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

interface ExternalEffectResolver
{
    /** Preview or apply an evidence-backed decision for an uncertain, inactive claim. */
    public function resolve(
        string $consumerName,
        string $messageId,
        ExternalEffectResolution $resolution,
        RecoveryEvidence $evidence,
        bool $apply = false,
    ): void;
}
