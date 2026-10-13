# ADR-0034: Notification Providers and Partial Delivery Results

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Applications need typed email, push, and SMS requests. A grouped request can partially succeed because provider calls cannot be rolled back together.

[Notification guidance](../platform/20-notifications.md) defines provider selection, ordered results, and partial failures.

- **Constraints:** Validate requests before provider calls. Keep vendor types and secrets private. Distinguish provider acceptance from end-user delivery.

## Considered Options

### Option A: Typed notification port with provider adapters and partial results

- **Pros:** Callers use provider-independent requests. Completed deliveries remain visible when a later item fails.
- **Cons / Risks:** Provider guarantees differ. A partial batch cannot be safely resent without checking completed and uncertain outcomes.
- **Estimated Cost / Effort:** Medium

### Option B: Direct vendor calls returning one batch success flag

- **Pros:** Initial integration needs fewer project models.
- **Cons / Risks:** Vendor details enter callers. One flag hides completed deliveries and uncertain results.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Typed requests and explicit partial results preserve information needed for recovery. Delivery outcomes cannot be represented by one batch boolean.

## Consequences

- **Positive:** [StackNotifier](../../src/Backendbase/Infrastructure/Adapters/Notification/StackNotifier.php) checks grouped provider availability before sending. Results retain message identifiers in delivery order.
- **Negative / Trade-offs:** Provider acceptance does not prove final delivery. SES automatic retries are disabled. No notification queue consumer is registered.

## Implementation & Validation

- **Rollout Steps:** Register one provider per enabled notification type. Validate complete requests. Preserve completed results and the failed item index.
- **Verification:** Use provider doubles for request mapping, ordered results, unavailable providers, partial failure, and uncertain outcomes. Do not send live notifications from automated tests.
- **Review Date:** Review when providers, delivery guarantees, batch recovery, or queued notification behavior change.
