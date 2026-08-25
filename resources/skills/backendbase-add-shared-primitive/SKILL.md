---
name: backendbase-add-shared-primitive
description: Add a stable cross-context value, identifier, query value, or serialized primitive to Backendbase-style Shared code. Do not use for a business-specific value owned by one bounded context.
---

# Add a shared primitive

## Outcome

Add one small type with stable cross-context meaning, enforced construction invariants, one public representation, and focused invalid-boundary and serialization tests.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, architecture boundaries, container definitions, tests, the owning contexts, and the nearest primitive.
3. Prove that the meaning is stable across contexts rather than merely duplicated.
4. Resolve construction, normalization, comparison, serialization, exception, nullability, and sensitive-value rules.
5. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).

Keep the type in its bounded context when status, role, limit, lifecycle, or policy belongs to one model.

## Target-project adaptation

Use the target namespace, identifier format, exception hierarchy, serialization contract, normalization policy, PHP version, and tests. Never copy Backendbase sentinel meanings, business values, personal identifiers, example data, or weak legacy validation.

## Workflow

1. State the stable meaning and why Shared owns it.
2. Define the smallest precise value and method set.
3. Enforce all true invariants at construction or a named constructor.
4. Normalize only when the contract requires it.
5. Provide one documented scalar or JSON representation.
6. Translate invalid external values to the target's stable public exception.
7. Test valid, invalid, boundary, equality or conversion, and serialization behavior.
8. Update documentation for any public representation or limitation.

## Backendbase invariants

- Shared contains stable technical meaning, not context-owned business rules.
- Invalid objects cannot be constructed.
- Parameter, property, and return types are precise.
- Sensitive values are not logged or exposed through accidental serialization.
- UUID-based entity identifiers use UUIDv7 in the current platform.
- A null sentinel has no meaning unless the surrounding contract defines it.
- Filter field names require a repository allowlist before SQL use.

## Verification

```sh
vendor/bin/phpunit tests/Shared/Primitives/{PrimitiveTest}.php
vendor/bin/phpunit tests/Shared/Primitives
composer phpstan
composer cs-check
```

## Completion report

Report the ownership decision, invariant, public representation, exception, boundary tests, and skipped checks.
