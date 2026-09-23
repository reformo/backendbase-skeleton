---
name: backendbase-add-object-mapped-input
description: Map a sanitized boundary array into a typed input object with the Backendbase ObjectMapper pattern. Use for request or message input; do not use for response serialization or general object conversion.
---

# Add an object-mapped input

## Outcome

Add one typed boundary input whose accepted fields, normalization, scalar strictness, unknown-key policy, public errors, cache wiring, and tests are explicit.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the owning input boundary, installed mapper version, mapping policy, nearest typed input, and boundary tests.
3. Resolve the external payload contract, target object, required and optional fields, normalization, unknown-key behavior, scalar strictness, and public error schema.
4. Confirm that object mapping is simpler and safer than explicit construction for this boundary.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not create a new request schema or silently broaden accepted input without user intent.

## Target-project adaptation

Use the target DTO style, mapper library and version, sanitizer, exception hierarchy, cache path, environment modes, and test helpers. Never copy Backendbase target class names, payload values, cache paths, secrets, or permissive behavior without a contract decision.

## Workflow

1. Define the exact external field set and target property types.
2. Sanitize the boundary array before mapping.
3. Apply a small normalization callback only for documented conversions.
4. Decide whether unknown fields are rejected or intentionally removed.
5. Decide whether scalar coercion is acceptable; validate explicitly when exact types matter.
6. Map to an immutable typed input and translate mapping failures to the stable input exception.
7. Wire cache behavior through the target container.
8. Test valid mapping, normalization, missing values, invalid types, unknown fields, and public error context.

## Backendbase invariants

- `PayloadSanitizer` runs before the optional normalization callback.
- Only public declared target fields survive current field filtering.
- Valinor failures become `InvalidUserInput` with short target name and field-indexed errors.
- Valinor internals do not reach controllers or clients.
- Unknown-key removal and permissive scalar conversion are explicit risks, not validation substitutes.
- Exact input contracts use boundary validation before mapping.
- Target public fields equal the intended accepted contract.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Mapping/ObjectMapperTest.php
composer phpstan
composer complexity
composer cs-check
```

Add a focused owning-boundary test when the mapped input belongs outside Shared tests.

## Completion report

Report the target input, accepted fields, normalization, unknown-key and scalar policies, error shape, tests, and blocked required checks.
