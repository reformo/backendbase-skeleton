<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use LogicException;

interface ExternalEffectInbox
{
    /**
     * Start the external effect after its claim commits. A completed message skips the effect.
     * An active claim blocks another attempt. An unknown outcome requires evidence-backed resolution.
     * Expiry alone never permits another provider call.
     * The caller must not have an active database transaction on the claim connection.
     *
     * @param callable(): void $externalEffect
     *
     * @throws ExternalEffectInProgress
     * @throws ExternalEffectOutcomeUnknown
     * @throws LogicException
     */
    public function processOnce(
        string $consumerName,
        string $messageId,
        string $effectName,
        callable $externalEffect,
    ): void;
}
