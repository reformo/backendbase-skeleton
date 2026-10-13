# ADR-0027: Domain Aggregates and Persistence Records

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Database mapping must not determine aggregate behavior. Persistence adapters need a complete, typed representation without exposing ORM objects to application callers.

The [persistence rules](../platform/09-persistence.md) separate aggregate snapshots, Doctrine records, and read models.

The [final decision report](references/2026-10-13-identity-access-decisions.md#references-transactions-and-lifecycle) retains domain ownership and existing mapping conventions. Aggregate references use identifiers with explicit lifecycle coordination.

- **Constraints:** Keep Doctrine metadata and vendor UUID conversion inside adapters. Reconstitute through domain factories. Preserve typed identifier references and invariants for stored data.

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
- **Negative / Trade-offs:** The mapping layer needs complete round-trip tests. ORM associations and cascades must not introduce physical foreign keys or implicit domain lifecycle changes.

## Implementation & Validation

- **Rollout Steps:** Preserve typed snapshots and domain factories. Apply [assigned UUID mapping](0040-assigned-uuid-identifiers-in-doctrine.md) inside adapters. Use explicit reference and lifecycle protocols.
- **Verification:** Use production metadata and repository contracts for round trips, enums, nulls, malformed rows, and rollback. Check scalar references and absence of unintended foreign keys or cascades.
- **Review Date:** Review when aggregate persistence or record representation changes.
