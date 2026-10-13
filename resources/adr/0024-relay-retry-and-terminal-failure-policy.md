# ADR-0024: Relay, Retry, and Terminal-Failure Policy

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Broker outages must leave publication intent recoverable. Consumer contract failures and transient execution failures need different treatment.

The [outbox policy](../platform/11-messaging-outbox.md) and [consumer policy](../platform/12-messaging-consumers.md) use separate retry rules.

- **Constraints:** Use UTC clock values. Preserve pending publication intent. Keep permanent contract failures out of repeated execution.

## Considered Options

### Option A: Separate outbox publication and consumer retry policies

- **Pros:** Publication can recover after prolonged broker failure. Consumer poison messages become terminal.
- **Cons / Risks:** Publication has no terminal attempt limit. Persistent publication failures need monitoring and repair.
- **Estimated Cost / Effort:** Medium

### Option B: One retry limit for publication and consumption

- **Pros:** One common policy is easier to configure.
- **Cons / Risks:** A publication limit can abandon committed facts. Unlimited consumption can repeatedly execute poison messages.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Publication intent and consumer execution have different recovery needs. Their failure policies must preserve those differences.

## Consequences

- **Positive:** Outbox claims last 60 seconds and retry delays grow from 2 to 256 seconds. Transient consumer failures become terminal after five attempts.
- **Negative / Trade-offs:** Permanent mapping or contract failures reject immediately. Concurrent relays and retries do not guarantee strict message ordering.

## Implementation & Validation

- **Rollout Steps:** Keep retry calculations in messaging policy services. Use claim tokens and eligible-row locking. Supervise the continuous relay and schedule status checks.
- **Verification:** Test claim expiry, retry delay boundaries, permanent rejection, and the fifth transient failure. Check [retry policy](../../src/Backendbase/Infrastructure/Messaging/OutboxRetryPolicy.php) and pending-row age.
- **Review Date:** Review when outage targets, ordering requirements, or failure classification change.
