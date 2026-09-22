---
name: backendbase-add-domain-value-object
description: Create or change a Backendbase value object that owns a domain or stable shared invariant and one representation. Use for typed business values and identifiers; do not use for passive data transfer objects, read models, or unvalidated primitive wrappers.
---

# Add a Backendbase Domain Value Object

## Outcome

Replace a business-bearing primitive with a valid-by-construction type in the correct ownership boundary.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the nearest value object, affected domain invariant, callers, serialization, and focused tests.
3. Search all current callers and serialized forms of the value.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Decide whether the meaning belongs to one context or is stable across contexts.

## Target-project adaptation

Adapt the invariant, normalization policy, exception type, equality needs, and scalar or JSON representation. Do not copy Backendbase Email, Name, UUID, or Example names as business rules.

## Workflow

1. Define the exact valid set, boundary cases, and canonical representation.
2. Place context-specific meaning under the owning context. Use Shared only for stable cross-context meaning.
3. Enforce the invariant during construction or a named constructor.
4. Add only behavior required by callers. Avoid trivial setters and speculative conversion methods.
5. Update boundary conversion, contracts, and persistence mapping only where the new type is used.
6. Test invalid, boundary, valid, equality, and serialization behavior as applicable.

## Backendbase invariants

- Do not move a type to Shared because two classes use it.
- Do not silently trim, lowercase, coerce, or substitute a default unless the contract requires it.
- Keep one stable meaning for each representation method.
- Translate invalid external values into the project's public typed failure where required.
- Keep sensitive values out of logs and serialized diagnostics.
- Do not add database columns or migration work merely because a wrapper type was added.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run the focused value-object tests, affected contract or mapper tests, PHPStan level 8, the configured complexity check, and PHPCS. Run architecture tests when placement or dependencies changed.

## Completion report

Report ownership, invariant, normalization decision, representation, changed callers, commands run, and any blocked required check.
