# ADR-0025: Replay and Deduplication Retention

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Cleanup can remove completed message records. A duplicate arriving after inbox deletion can execute again. Replay authority and supported replay age are not fully defined.

The [cleaner](../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationMessageLogCleaner.php) enforces at least 30 retention days. [Queue guidance](../platform/13-queue-runtime.md) provides no automatic replay command.

- **Constraints:** Align broker retention, recovery delay, inbox retention, and replay age. Preserve pending and unresolved records. Define whether replay preserves message identity.

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

- **Chosen Option:** Not selected. The cleanup minimum does not establish the permitted replay window.
- **Rationale:** Select retention from maximum message age and required recovery. Define event-version support and message identity before enabling replay.

## Consequences

- **Positive:** An accepted policy will make duplicate protection and replay limits explicit.
- **Negative / Trade-offs:** Replay can repeat effects when identity changes or deduplication evidence has expired.

## Implementation & Validation

- **Rollout Steps:** Measure required recovery age. Select retention and identity rules. Define operator authority, preview, execution, and recovery procedures.
- **Verification:** Test duplicates before and after retention, exact cleanup cutoffs, unsupported versions, and replay identity behavior.
- **Review Date:** Review before enabling replay or changing message-record retention.
