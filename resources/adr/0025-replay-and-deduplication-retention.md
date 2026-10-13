# ADR-0025: Replay and Deduplication Retention

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Cleanup can remove completed message records. A duplicate arriving after inbox deletion can execute again. Replay authority and supported replay age are not fully defined.

The [cleaner](../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationMessageLogCleaner.php) enforces at least 30 retention days. [Queue guidance](../platform/13-queue-runtime.md) provides no automatic replay command.

The target policy uses a bounded replay window with matching inbox retention. Exact replay age, its reference timestamp, and retention durations remain pending. The cleanup minimum does not establish the replay window. Replay tooling and age enforcement remain to be implemented.

- **Constraints:** Align broker retention, recovery delay, inbox retention, and replay age. Retain completion evidence through all permitted repeat deliveries and their processing delays. Preserve pending and unresolved records and the completion evidence needed for their recovery. Ordinary replay must preserve the original consumer identity and message ID. Reject replay outside the supported window. Broker requeue or redrive must not reset replay eligibility.

## Considered Options

### Option A: Bounded replay window with matching inbox retention

- **Pros:** Storage and duplicate protection have explicit limits. Operators can reject replay outside the supported window.
- **Cons / Risks:** Older recovery requires a separate procedure. The window must cover broker and incident recovery delays.
- **Estimated Cost / Effort:** Medium

### Option B: Long-term archive with extended deduplication records

- **Pros:** Older facts can remain available for controlled recovery or rebuilding projections.
- **Cons / Risks:** Storage, privacy, schema compatibility, and replay tooling require additional policy and work.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A: Bounded replay window with matching inbox retention.
- **Rationale:** A bounded recovery period fits the existing message logs and cleanup model. Matching inbox retention preserves duplicate protection throughout the supported replay period and delivery delay. Stable message and consumer identity lets ordinary replay reuse existing completion evidence.

## Consequences

- **Positive:** Operators have an explicit supported recovery period. Cleanup can remove completed records after their protection obligations end. Replay and duplicate protection follow one documented retention policy.
- **Negative / Trade-offs:** Older recovery requires a separate reviewed procedure. Identity changes bypass existing inbox records. Dead-letter waiting and delayed publication can outlast a nominal window unless retention and replay controls cover them. External effects remain subject to [manual outcome resolution](0026-external-effect-idempotency-and-unknown-outcomes.md).

## Implementation & Validation

- **Rollout Steps:** Measure required recovery age and delivery delays. Define the replay-age reference timestamp and maximum window. Select record and broker retention with a delivery and recovery margin. Align cleanup with pending and unresolved recovery needs. Implement age checks and identity preservation before enabling replay. Define operator authority, preview, execution records, and recovery procedures. Use a separate processing scope for projection rebuilds.
- **Verification:** Test duplicate protection inside the window and rejection of expired replays before handler execution. Test exact cleanup cutoffs and replay processing near the window boundary. Preserve pending and unresolved records and required completion evidence. Test unsupported versions, original message and consumer identity, and external-effect recovery restrictions. Verify broker redrive does not reset replay eligibility.
- **Review Date:** Review before enabling replay or changing message-record retention.
