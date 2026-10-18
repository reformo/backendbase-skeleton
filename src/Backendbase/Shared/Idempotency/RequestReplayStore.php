<?php

declare(strict_types=1);

namespace Backendbase\Shared\Idempotency;

interface RequestReplayStore
{
    /** Return null for a new claim, or the encrypted response for a completed claim. */
    public function claim(RequestIdentity $identity): string|null;

    public function complete(RequestIdentity $identity, string $response): void;

    /** Delete one bounded batch of expired completed responses. Never delete unresolved claims. */
    public function deleteExpired(): int;
}
