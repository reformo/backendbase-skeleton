<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

interface ExternalEffectInbox
{
    /**
     * Start the external effect at most once for the message.
     *
     * @param callable(): void $externalEffect
     *
     * @throws ExternalEffectInProgress
     * @throws ExternalEffectOutcomeUnknown
     */
    public function processOnce(
        string $consumerName,
        string $messageId,
        string $effectName,
        callable $externalEffect,
    ): void;
}
