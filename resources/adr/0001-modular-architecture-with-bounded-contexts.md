# ADR-0001: Modular Architecture with Bounded Contexts

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform must support separate business capabilities within one backend. Each capability needs its own language, rules, contracts, adapters, and tests.

A bounded context defines this ownership boundary. The current platform assembles contexts in one application. See [architecture rules](../platform/02-architecture.md) and [module structure](../platform/03-bounded-contexts.md).

- **Constraints:** Preserve business ownership, simple deployment, and module isolation. Do not require separate services before an operational need exists.

## Considered Options

### Option A: Modular application with bounded contexts

- **Pros:** One deployment can contain independent business modules. Module ownership and dependencies remain visible.
- **Cons / Risks:** Modules share a process and deployment lifecycle. Dependency rules need automated checks.
- **Estimated Cost / Effort:** Medium

### Option B: Separate service for each business capability

- **Pros:** Each service can have its own deployment, capacity, and failure boundary.
- **Cons / Risks:** Network contracts, distributed operations, and data consistency increase delivery effort.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The platform already provides context boundaries without requiring distributed service operations. Independent deployment can follow a demonstrated need.

## Consequences

- **Positive:** Business modules can evolve behind contracts and retain their tests when moved.
- **Negative / Trade-offs:** A process failure can affect several contexts. Context isolation does not provide deployment isolation.

## Implementation & Validation

- **Rollout Steps:** Keep capability code within its context. Register each context through its composition root. Review extraction when independent operation is necessary.
- **Verification:** Run [architecture tests](../../tests/Architecture) for context isolation and dependency direction. Review new cross-context communication.
- **Review Date:** Review when a context needs independent deployment, scaling, or ownership.
