<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\ExternalEffectClaims;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\ExternalEffectOutcomeUnknown;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DoctrineExternalEffectInbox implements ExternalEffectInbox
{
    private ExternalEffectClaims $claims;

    public function __construct(Connection $connection, private Clock $clock, int $claimTtlSeconds = 300)
    {
        $this->claims = new ExternalEffectClaims($connection, $claimTtlSeconds);
    }

    public function processOnce(
        string $consumerName,
        string $messageId,
        string $effectName,
        callable $externalEffect,
    ): void {
        $clock      = $this->clock;
        $now        = $clock->now();
        $claims     = $this->claims;
        $claimToken = $claims->claim($consumerName, $messageId, $effectName, $now);
        if ($claimToken === null) {
            return;
        }

        try {
            $externalEffect();
        } catch (Throwable $exception) {
            $claims->markOutcomeUnknown($consumerName, $messageId, $claimToken);

            throw new ExternalEffectOutcomeUnknown(
                'The external effect started, but its outcome is unknown.',
                previous: $exception,
            );
        }

        try {
            $processedAt = $clock->now();
            $claims->complete($consumerName, $messageId, $claimToken, $processedAt);
        } catch (Throwable $exception) {
            throw new ExternalEffectOutcomeUnknown(
                'The external effect completed, but its completion could not be recorded.',
                previous: $exception,
            );
        }
    }
}
