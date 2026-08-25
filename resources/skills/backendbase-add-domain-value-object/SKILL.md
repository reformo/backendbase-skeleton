---
name: backendbase-add-domain-value-object
description: Create or change a Backendbase value object that owns a domain or stable shared invariant and one representation. Use for typed business values and identifiers; do not use for passive data transfer objects, read models, or unvalidated primitive wrappers.
---

# Add a Backendbase Domain Value Object

## Outcome

Replace a business-bearing primitive with a valid-by-construction type in the correct ownership boundary.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, layer rules, container layout, test roots, and the nearest value object.
3. Search all current callers and serialized forms of the value.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
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

Run the focused value-object tests, affected contract or mapper tests, PHPStan level 8, and PHPCS. Run architecture tests when placement or dependencies changed.

## Completion report

Report ownership, invariant, normalization decision, representation, changed callers, commands run, and any skipped check.
