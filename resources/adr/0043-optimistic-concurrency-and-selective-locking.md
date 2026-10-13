# ADR-0043: Optimistic Concurrency and Selective Locking

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Concurrent updates can lose state or create references during lifecycle changes. Different invariants need different concurrency controls.

The [decision report](references/2026-10-13-identity-access-decisions.md#references-transactions-and-lifecycle) selects optimistic state versions, selective locks, and full-transaction retry. These target controls require implementation and real MySQL verification.

- **Constraints:** Commit coupled writes together. Keep every writer on one lock protocol. Keep reporting free of unnecessary locks. Preserve the current isolation level unless an exception is verified.

## Considered Options

### Option A: Optimistic versions with selective pessimistic protection

- **Pros:** Ordinary updates detect stale state. Reference and lifecycle races receive explicit database protection.
- **Cons / Risks:** Lock order, error classification, and retry behavior require careful coordination. Contention can affect latency.
- **Estimated Cost / Effort:** High

### Option B: Optimistic versions for every invariant without relationship locks

- **Pros:** Normal writes use a simpler concurrency model.
- **Cons / Risks:** A version check on one aggregate does not alone protect concurrent reference creation, lifecycle changes, or shared capacity.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report uses integer ORM Version state for normal updates. This version is independent of the generated UUID display field. Relationship creation uses read locks while lifecycle transitions use write locks.

## Consequences

- **Positive:** Shared counters and capacity can use exclusive locks or atomic conditional updates. Historical reads use an appropriate consistent snapshot.
- **Negative / Trade-offs:** Deadlocks and lock timeouts need distinct diagnostics and bounded retry. A failed ORM attempt can require a fresh EntityManager and UnitOfWork.

## Implementation & Validation

- **Rollout Steps:** Define transaction ownership and lock order. Flush pending ORM UnitOfWork state before commit through an ORM-aware transaction adapter. Add versions and selective locks. Fully roll back before eligible whole-operation retries.
- **Verification:** Use two real MySQL connections for stale updates, reference/removal races, counter contention, deadlocks, and lock timeouts. Verify rollback and fresh-state retry.
- **Review Date:** Review when invariants, writer paths, retry limits, or command-specific isolation requirements change.
