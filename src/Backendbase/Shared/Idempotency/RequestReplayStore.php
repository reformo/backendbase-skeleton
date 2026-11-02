<?php

declare(strict_types=1);

namespace Backendbase\Shared\Idempotency;

interface RequestReplayStore
{
    /**
     * Claim a new request or return the encrypted response for a matching completed request.
     * Return null only when this call owns a new claim.
     * Reject changed fingerprints and matching unresolved claims without executing the request.
     * Only an expired completed claim can be replaced.
     *
     * @throws RequestMismatch
     * @throws RequestOutstanding
     */
    public function claim(RequestIdentity $identity): string|null;

    /** Complete a matching unresolved claim. Retain its encrypted response for 24 hours after completion. */
    public function complete(RequestIdentity $identity, string $response): void;

    /** Delete one bounded batch of expired completed responses. Never delete unresolved claims. */
    public function deleteExpired(): int;
}
