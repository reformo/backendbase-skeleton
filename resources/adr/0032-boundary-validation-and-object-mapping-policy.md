# ADR-0032: Boundary Validation and Object-Mapping Policy

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

HTTP boundaries use explicit validation, while the shared object mapper sanitizes arrays, removes unknown keys, and permits some type conversion. Strictness is not uniform.

The target policy requires strict boundary validation before mapping. Exceptions must be explicit in the affected input contract. Caller inventory, compatibility review, and runtime adoption remain pending.

The [mapping guidance](../platform/23-object-mapping.md) describes current [ObjectMapper](../../src/Backendbase/Infrastructure/Adapters/Mapping/ObjectMapper.php) behavior.

- **Constraints:** Reject invalid required fields, types, formats, sizes, and ranges. Reject unknown fields and implicit scalar conversion by default. Document each permitted normalization, conversion, or unknown-field exception in the affected input contract.

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

- **Chosen Option:** Option A: Strict boundary validation with explicit mapping exceptions.
- **Rationale:** Strict validation exposes invalid input at the trust boundary and prevents silent mapper behavior from defining the public contract. Contract-specific exceptions make intentional normalization and compatibility behavior explicit. Typed commands carry the validated values into application logic.

## Consequences

- **Positive:** Invalid fields and scalar types produce visible boundary errors before mapping. Documented exceptions give normalization and unknown-field handling explicit limits.
- **Negative / Trade-offs:** Existing callers may rely on ignored fields or scalar conversion. Adoption can require a versioned contract or a transition period. Each mapping exception needs contract documentation and behavior tests.

## Implementation & Validation

- **Rollout Steps:** Inventory mapping callers and boundary parsers. Review published contracts and compatibility. Document each permitted exception. Add behavior tests before changing existing public inputs. Apply strict boundary validation before mapping into typed commands or input objects.
- **Verification:** Test unknown keys, exact scalar types, nulls, omitted fields, malformed values, oversized input, and safe field-level errors. Verify default rejection before mapping. Test each documented exception and confirm it preserves the declared contract without exposing mapper internals.
- **Review Date:** Review before adding a new mapped public input or changing mapper permissiveness.
