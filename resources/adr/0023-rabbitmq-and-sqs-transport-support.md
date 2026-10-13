# ADR-0023: RabbitMQ and SQS Transport Support

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Deployments can need a local broker or a managed queue. Application code must use project message contracts while transport outcomes remain explicit.

[Queue runtime guidance](../platform/13-queue-runtime.md) and [queue composition](../../config/dependencies/queue.php) select RabbitMQ or Amazon Simple Queue Service (SQS).

- **Constraints:** Keep vendor objects inside adapters. Define acknowledgment, retry, and rejection for each driver. Do not assume identical routing or redrive behavior.

## Considered Options

### Option A: Selectable RabbitMQ and SQS adapters behind queue ports

- **Pros:** Application messaging uses stable ports. Deployment can select the supported transport.
- **Cons / Risks:** Visibility, requeue, rejection, and dead-letter configuration differ. Both adapters need contract and failure tests.
- **Estimated Cost / Effort:** Medium

### Option B: One broker-specific API throughout application messaging

- **Pros:** Only one transport behavior needs implementation and operation.
- **Cons / Risks:** Broker replacement affects application code and business handlers.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The platform supports both deployment choices while retaining explicit transport differences. RabbitMQ remains the default.

## Consequences

- **Positive:** Typed message, subscription, and publication results cross the ports.
- **Negative / Trade-offs:** RabbitMQ requeues retries immediately. SQS waits for visibility timeout and requires infrastructure redrive policy for rejection.

## Implementation & Validation

- **Rollout Steps:** Select one queue driver in configuration. Configure its destination and dead-letter behavior. Operate relay and consumer processes separately.
- **Verification:** Test acknowledgment, retry, rejection, timeout, and safe diagnostics for each adapter. Confirm configured redrive behavior on prepared targets.
- **Review Date:** Review when a new driver or destination-routing requirement is introduced.
