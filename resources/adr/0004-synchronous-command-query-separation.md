# ADR-0004: Synchronous Command Query Responsibility Segregation

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Write operations enforce business rules. Read operations return use-case-specific data. Their contracts must express these separate responsibilities.

Command Query Responsibility Segregation (CQRS) separates these paths. The current [CQRS design](../platform/04-cqrs.md) uses synchronous buses.

- **Constraints:** Commands return no application result. Queries return typed read models. Bus dispatch must not hide transaction or authorization policy.

## Considered Options

### Option A: Separate synchronous command and query buses

- **Pros:** Each operation has a clear purpose. Dispatch completes within the current process.
- **Cons / Risks:** Handlers must define transactions and policy explicitly. A command does not directly return a resource representation.
- **Estimated Cost / Effort:** Medium

### Option B: Unified service methods for reads and writes

- **Pros:** Callers use fewer contracts and can receive write results directly.
- **Cons / Risks:** Read and write responsibilities can mix. Use-case contracts become less explicit.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Separate contracts preserve write invariants and read projections without requiring asynchronous command processing.

## Consequences

- **Positive:** Command completion is observable before the caller responds. Read models can change independently from aggregates.
- **Negative / Trade-offs:** A resource response after a write can require an application orchestrator and a subsequent read.

## Implementation & Validation

- **Rollout Steps:** Register each command and query handler. Keep policy in handlers or application orchestration. Preserve void command contracts.
- **Verification:** Check [bus contracts](../../src/Backendbase/Shared/CQRS) and [handler registry tests](../../tests/Architecture). Test completion, failure propagation, and query results.
- **Review Date:** Review when commands require asynchronous acceptance or a different client-visible completion contract.
