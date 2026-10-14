# ADR-0039: UUIDv7 Identifiers and Binary Storage

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Entity identifiers need independent application generation and one consistent representation across storage, references, APIs, and tokens.

The [decision report](references/2026-10-13-identity-access-decisions.md#entity-identifier-representation) selects UUID version 7 (UUIDv7), binary storage, and a readable virtual projection. Identity records use assigned natural binary UUIDv7 identifiers. Technical message identities retain their existing wire strings. ExampleCatalog also uses a binary UUID primary key. Its conversion reuses each existing public UUID and preserves HTTP fields.
New Example entries allocate UUIDv7. The integer `typeTargetId` remains an opaque selector.

- **Constraints:** Use natural byte order. Keep binary identifiers out of JSON and JWT. Do not use UUID generation time as historical business time.

## Considered Options

### Option A: UUIDv7 BINARY(16) identity with a virtual readable projection

- **Pros:** Applications assign identity before persistence. One stored identifier supports compact binary references and readable diagnostics.
- **Cons / Risks:** Keys are wider than integer keys. Existing storage and reference contracts need inventory and compatibility checks.
- **Estimated Cost / Effort:** Medium

### Option B: Integer primary keys with separately stored public UUIDs

- **Pros:** Integer primary keys use less raw key space.
- **Cons / Risks:** Two persisted identities require mapping. The approved design uses the binary UUID as the real identity.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report chooses one application-generated UUIDv7 identity. Physical entity IDs and logical references use BINARY(16) with no UUIDv1 byte swapping.

## Consequences

- **Positive:** The readable column is uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin, generated virtually from BIN_TO_UUID(id, 0).
- **Negative / Trade-offs:** The projection has no normal lookup index and is not a relationship key. Technical message, claim, and cache identifier exceptions require separate inventory.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Generate UUIDv7 with Ramsey. Use binary IDs for storage and joins. Use UUID_TO_BIN(:uuid, 0) for SQL lookup conversion where needed.
- **Verification:** Test 16-byte persistence, string round trips, equal byte order across references, read-only projection, and absence of swap flag 1. Benchmark real query and index costs.
- **Review Date:** Review when identifier scope, wire compatibility, or measured storage requirements change.
