# ADR-0027: Domain Aggregates and Persistence Records

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Database mapping must not determine aggregate behavior. Persistence adapters need a complete, typed representation without exposing ORM objects to application callers.

The [persistence rules](../platform/09-persistence.md) separate aggregate snapshots, Doctrine records, and read models.

- **Constraints:** Keep Doctrine metadata outside Domain. Reconstitute state through domain factories. Preserve invariants for data loaded from storage.

## Considered Options

### Option A: Separate domain aggregates, typed snapshots, and ORM records

- **Pros:** Aggregate rules remain independent of storage. Mapping has an explicit typed boundary.
- **Cons / Risks:** Snapshot and record mapping must change together. Reconstitution and invalid stored data require adapter tests.
- **Estimated Cost / Effort:** Medium

### Option B: Use ORM entities as domain aggregates

- **Pros:** One object model reduces mapping code.
- **Cons / Risks:** Persistence metadata and lifecycle can enter domain rules. Storage changes affect business objects directly.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Typed snapshots give adapters the required state while keeping database representation outside domain behavior.

## Consequences

- **Positive:** Domain code can run without Doctrine. Persistence records remain private to adapters.
- **Negative / Trade-offs:** The extra mapping layer requires complete round-trip verification.

## Implementation & Validation

- **Rollout Steps:** Define typed snapshots for aggregate persistence. Map records at the adapter boundary. Reconstitute through domain factories.
- **Verification:** Use production metadata and common repository contracts. Test round trips, enums, null fields, malformed rows, and rollback under [testing guidance](../platform/17-testing.md).
- **Review Date:** Review when aggregate persistence or record representation changes.
