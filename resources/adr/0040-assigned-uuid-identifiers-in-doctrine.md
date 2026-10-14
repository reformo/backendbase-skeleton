# ADR-0040: Assigned UUID Identifiers in Doctrine

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Doctrine must persist the approved UUID without database-generated replacement or domain dependence on ORM types.

The [decision report](references/2026-10-13-identity-access-decisions.md#entity-identifier-representation) selects ramsey/uuid-doctrine uuid_binary. [Type registration](../../config/doctrine-types.php) already exists; Identity and Example mappings use assigned identifiers. Legacy UUID and new UUIDv7 round trips are verified on MySQL 8.4.

- **Constraints:** Assign identity before persist. Preserve existing mapping conventions. Keep Doctrine and Ramsey conversion at the persistence adapter boundary.

## Considered Options

### Option A: Assigned UUID identifiers using the uuid_binary type

- **Pros:** Persistence keeps the identifier selected by the application or aggregate factory. Existing Ramsey integration can be reused.
- **Cons / Risks:** Adapters must map project identifier values consistently. Doctrine hydration and identity-map behavior require real adapter tests.
- **Estimated Cost / Effort:** Medium

### Option B: Database-generated or ordered-time UUID mapping

- **Pros:** Persistence can own identifier allocation or an alternative byte layout.
- **Cons / Risks:** Allocation occurs after application construction or changes byte order. UUIDv1 ordered-time mapping does not match the selected UUIDv7 standard.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The approved mapping uses an assigned identifier strategy with no ORM GeneratedValue. uuid_binary preserves the selected natural UUID byte representation.

## Consequences

- **Positive:** Domain identifiers can remain immutable project-owned values. ORM records handle vendor-specific UUID conversion.
- **Negative / Trade-offs:** Custom types for multiple domain identifier classes require a demonstrated need. The readable generated field has a separate [mapping choice](0047-generated-uuid-projection-mapping.md).

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Reuse registered uuid_binary support. Map record identity as assigned. Convert typed domain identity inside adapters. Preserve current attribute or XML conventions.
- **Verification:** Test persist and hydrate through the production container and real MySQL. Verify stable identity values, record lookup, byte order, and reviewed schema differences.
- **Review Date:** Review when Doctrine or Ramsey versions, identifier value objects, or mapping conventions change.
