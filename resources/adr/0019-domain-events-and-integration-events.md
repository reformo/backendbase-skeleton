# ADR-0019: Domain Events and Integration Events

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A context needs internal reactions to domain changes. Other modules or services need stable facts without receiving aggregate objects or internal implementation types.

[Architecture rules](../platform/02-architecture.md) separate domain behavior from infrastructure. [Integration-event contracts](../platform/05-integration-event-contracts.md) define published facts.

- **Constraints:** Keep domain events within their business boundary. Give integration events explicit names, versions, and typed payloads.

## Considered Options

### Option A: Separate domain events from versioned integration events

- **Pros:** Internal reactions can evolve with the domain. Published contracts remain explicit and stable.
- **Cons / Risks:** Application code must create the integration payload. Domain and integration events need separate registration and tests.
- **Estimated Cost / Effort:** Medium

### Option B: Publish domain objects directly as external events

- **Pros:** Less translation code is needed initially.
- **Cons / Risks:** Internal changes affect consumers. Persistence and business object structure can become public contracts.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The two event types serve different ownership and compatibility boundaries. Explicit integration payloads prevent accidental publication of internal state.

## Consequences

- **Positive:** Domain listeners remain synchronous and context-local. Integration facts can use local dispatch and optional queue delivery.
- **Negative / Trade-offs:** Event names and payload versions require maintenance beyond domain refactoring.

## Implementation & Validation

- **Rollout Steps:** Register domain listeners within their context. Define complete integration payloads in Contracts. Select queue delivery explicitly.
- **Verification:** Check domain-listener targets and container resolution. Test integration payloads through the real consumer registry and dispatcher.
- **Review Date:** Review when an internal event becomes a published contract or new consumers require compatibility.
