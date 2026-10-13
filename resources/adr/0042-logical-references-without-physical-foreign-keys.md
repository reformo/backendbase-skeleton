# ADR-0042: Logical References without Physical Foreign Keys

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Related aggregates need valid references without database-owned lifecycle behavior. The application must protect reference invariants under concurrent creation and removal.

The [decision report](references/2026-10-13-identity-access-decisions.md#references-transactions-and-lifecycle) selects indexed logical references with no physical foreign-key constraints.

- **Constraints:** Store logical references as BINARY(16). Retain applicable primary keys, uniqueness, nullability, and indexes. Require every writer path to follow the same validation protocol.

## Considered Options

### Option A: Domain-controlled logical references with transaction and locking

- **Pros:** Relationship and lifecycle policy remains in Domain and Application. Storage contains explicit indexed references.
- **Cons / Risks:** The database does not automatically prevent orphan references. Background jobs and bulk writes must preserve the same invariants.
- **Estimated Cost / Effort:** High

### Option B: Physical foreign keys and automatic ORM cascades

- **Pros:** The database can enforce basic reference existence for all SQL writers.
- **Cons / Risks:** Cascades can perform lifecycle work outside explicit domain orchestration. This option conflicts with the selected no-foreign-key policy.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report explicitly chooses application-owned relationship integrity. This is a system decision, not a requirement of Domain-Driven Design.

## Consequences

- **Positive:** Aggregate relationships use independent identifier references. No readable UUID projection becomes a relationship key.
- **Negative / Trade-offs:** Integrity needs reconciliation and concurrent-writer tests. Physical database ownership remains open in [ADR-0008](0008-database-ownership-between-bounded-contexts.md).

## Implementation & Validation

- **Rollout Steps:** Inventory references and writers. Define existence, access, and lifecycle checks. Use the [common locking protocol](0043-optimistic-concurrency-and-selective-locking.md). Guard ORM-generated schema changes.
- **Verification:** Use real parallel MySQL connections to test reference creation against lifecycle transitions. Check migrations for unintended foreign keys, cascades, missing indexes, and orphan records.
- **Review Date:** Review when writer paths, relationship invariants, or database enforcement requirements change.
