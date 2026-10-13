# ADR-0002: Ports and Adapters with Inward Dependencies

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Business behavior must remain testable when HTTP, database, queue, or provider implementations change. Technology dependencies must stop at adapters.

The [architecture rules](../platform/02-architecture.md) define Domain, Contracts, Application, Infrastructure, and Shared dependencies.

- **Constraints:** Keep domain code free of frameworks. Preserve project-owned interfaces at technology boundaries.

## Considered Options

### Option A: Ports and Adapters with inward dependencies

- **Pros:** Application behavior uses contracts. Adapters can change without changing domain rules.
- **Cons / Risks:** Interfaces, mapping, and composition add code. Dependency checks require maintenance.
- **Estimated Cost / Effort:** Medium

### Option B: Framework services used throughout business code

- **Pros:** Initial features need fewer boundaries and less mapping.
- **Cons / Risks:** Business behavior depends on framework APIs. Adapter replacement and isolated tests become harder.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Stable contracts protect business behavior from infrastructure changes. Existing architecture tests make these boundaries verifiable.

## Consequences

- **Positive:** Domain and application tests can use controlled adapters. HTTP and persistence concerns remain at their boundaries.
- **Negative / Trade-offs:** Changes that cross a port require coordinated contract and adapter updates.

## Implementation & Validation

- **Rollout Steps:** Define ports at external boundaries. Bind adapters in composition roots. Keep business decisions within Domain or Application.
- **Verification:** Run [architecture tests](../../tests/Architecture). Check domain purity, Application dependencies, and both directions between inbound and outbound adapters.
- **Review Date:** Review when a new technology cannot fit the current dependency rules.
