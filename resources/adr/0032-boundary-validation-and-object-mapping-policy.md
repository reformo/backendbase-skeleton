# ADR-0032: Boundary Validation and Object-Mapping Policy

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

HTTP boundaries use explicit validation, while the shared object mapper sanitizes arrays, removes unknown keys, and permits some type conversion. Strictness is not uniform.

The [mapping guidance](../platform/23-object-mapping.md) describes current [ObjectMapper](../../src/Backendbase/Infrastructure/Adapters/Mapping/ObjectMapper.php) behavior.

- **Constraints:** Reject invalid required fields, types, formats, sizes, and ranges. Define unknown-field and conversion behavior in public input contracts.

## Considered Options

### Option A: Strict boundary validation with explicit mapping exceptions

- **Pros:** Unknown fields and scalar errors become visible. Typed commands preserve validated input.
- **Cons / Risks:** Changing permissive endpoints can affect clients. Explicit normalizers and compatibility review need effort.
- **Estimated Cost / Effort:** Medium

### Option B: Permissive whitelist mapping with endpoint-specific strict checks

- **Pros:** Existing mapper behavior can remain. Extra fields can be ignored where the contract permits them.
- **Cons / Risks:** Silent field removal and conversion can hide client mistakes. Endpoint behavior can differ.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Not selected. Current mapper behavior remains until contract compatibility is reviewed.
- **Rationale:** Choose a default only after identifying callers and published input behavior. Strictness must be an explicit contract, not an accidental mapper effect.

## Consequences

- **Positive:** An accepted policy will make validation, normalization, and unknown-field handling consistent.
- **Negative / Trade-offs:** Strict adoption can require a versioned contract or a transition period.

## Implementation & Validation

- **Rollout Steps:** Inventory mapping callers and boundary parsers. Define defaults and documented exceptions. Review compatibility. Add behavior tests before changing code.
- **Verification:** Test unknown keys, exact scalar types, nulls, omitted fields, malformed values, oversized input, and safe field-level errors.
- **Review Date:** Review before adding a new mapped public input or changing mapper permissiveness.
