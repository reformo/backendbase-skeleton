# ADR-0026: External-Effect Idempotency and Unknown Outcomes

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A provider call cannot share a database transaction. A timeout or completion-record failure can leave uncertainty about whether an external effect occurred.

Idempotency permits repeated requests for one operation without repeating its external effect.

The [external-effect inbox](../platform/12-messaging-consumers.md) provides a 300-second claim and blocks automatic repetition of unknown outcomes. The target policy uses this lease with evidence-based manual outcome resolution. Provider-specific recovery procedures and queued-consumer integration remain pending.

- **Constraints:** Do not repeat an unknown outcome automatically. Lease expiry does not authorize a new provider call. Preserve stable operation identity. Separate provider acceptance from final delivery. Record provider evidence and operator decisions before resolving an uncertain outcome.

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

- **Chosen Option:** Option B: Inbox lease with manual outcome resolution.
- **Rationale:** The existing conservative lease mechanism blocks automatic repetition while an operation is active or its outcome is unknown. Operators resolve uncertainty through provider records and stable operation identity. Insufficient evidence keeps the operation blocked.

## Consequences

- **Positive:** Uncertain effects have an explicit manual recovery path. Completed duplicates acknowledge without another provider call. Expired incomplete claims remain blocked until their outcomes are resolved.
- **Negative / Trade-offs:** Manual resolution adds operator work and can delay delivery. A lease does not guarantee exactly-once external effects. The [current inbox implementation](../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInbox.php) is a foundation. No notification queue consumer currently uses it.

## Implementation & Validation

- **Rollout Steps:** Define operation identity and provider-specific accepted, completed, failed, and unknown states. Claim the inbox lease before each provider call. Assign manual resolution ownership and document the required provider evidence. Record resolution decisions. Permit a controlled new attempt only when evidence confirms that the provider did not accept the operation. Complete recovery procedures before queued delivery.
- **Verification:** Test active and expired claims, provider timeout after acceptance, completion-record failure, duplicates, and evidence-based manual resolution. Verify lease expiry alone never repeats a provider call. Verify insufficient evidence preserves the blocked state. Verify completed effects acknowledge and controlled retry requires confirmed absence of provider acceptance.
- **Review Date:** Review before adding a queued external-effect consumer or changing provider guarantees.
