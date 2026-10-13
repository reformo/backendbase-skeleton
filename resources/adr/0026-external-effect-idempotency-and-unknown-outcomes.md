# ADR-0026: External-Effect Idempotency and Unknown Outcomes

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A provider call cannot share a database transaction. A timeout or completion-record failure can leave uncertainty about whether an external effect occurred.

Idempotency permits repeated requests for one operation without repeating its external effect.

The [external-effect inbox](../platform/12-messaging-consumers.md) provides a 300-second claim and blocks automatic repetition of unknown outcomes. Production use still needs a provider-specific completion policy.

- **Constraints:** Do not repeat an unknown outcome automatically. Preserve stable operation identity. Separate provider acceptance from final delivery.

## Considered Options

### Option A: Provider idempotency keys with reconciliation

- **Pros:** A provider can identify repeated requests. Status lookup can resolve uncertain completion.
- **Cons / Risks:** Providers differ in key support, retention, and status guarantees. Each integration needs explicit mapping.
- **Estimated Cost / Effort:** High

### Option B: Inbox lease with manual outcome resolution

- **Pros:** The existing conservative lease mechanism can prevent automatic repeated effects.
- **Cons / Risks:** Unknown outcomes require operator evidence. Recovery can remain blocked when provider records are insufficient.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Not selected. Existing lease behavior remains the safety boundary for future queued external effects.
- **Rationale:** Select the completion and reconciliation contract for each provider before production use. A lease cannot prove that a provider call happened once.

## Consequences

- **Positive:** An accepted policy will give uncertain effects a defined recovery path.
- **Negative / Trade-offs:** The [current inbox implementation](../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInbox.php) is a foundation. No notification queue consumer currently uses it.

## Implementation & Validation

- **Rollout Steps:** Define operation identity and provider guarantees. Specify accepted, completed, failed, and unknown states. Add reconciliation before queued delivery.
- **Verification:** Test active and expired claims, provider timeout after acceptance, completion-record failure, duplicates, and evidence-based recovery.
- **Review Date:** Review before adding a queued external-effect consumer or changing provider guarantees.
