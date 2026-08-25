---
name: backendbase-model-domain-behavior
description: Add or change Backendbase aggregate behavior, factories, invariants, state transitions, or domain enums. Use for business behavior inside an existing context; do not use for persistence mapping, HTTP validation, or creating a whole bounded context.
---

# Model Backendbase Domain Behavior

## Outcome

Implement one requested business behavior in domain objects that remain valid without HTTP, persistence, queues, or a container.

## Required discovery

1. Read all target-project `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, layer rules, container layout, test roots, and the nearest production aggregate.
3. Read the aggregate's callers, persistence reconstitution path, and focused tests.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. State the invariant, accepted inputs, state transition, and failure behavior before editing.

## Target-project adaptation

Keep the target project's aggregate style, identifier type, clock, state representation, and exception policy. Do not copy the Example state shape, names, timestamps, or soft-removal model unless the requested domain requires them.

## Workflow

1. Write or extend focused tests for valid, invalid, and boundary behavior.
2. Put the decision and state change on the aggregate or a context-owned value object.
3. Separate creation from persisted-state reconstitution when storage restores historical state.
4. Keep public behavior intention-revealing. Expose only state required for persistence or collaboration.
5. Update record mapping only when the domain state contract changed and persistence is in scope.
6. Remove only code made obsolete by this change.

## Backendbase invariants

- Domain code must not import HTTP, Doctrine, queues, containers, application handlers, or infrastructure.
- Application handlers invoke domain behavior; adapters do not decide whether an operation is allowed.
- Preserve precise types and constructor invariants.
- Use context-owned enums or value objects for business-bearing primitives.
- Treat `Shared\Domain\Aggregate` as optional. Current production Example code does not extend it.
- Do not record a domain event unless the target project has an explicit publish or drain path.
- Do not add persistence fields, timestamps, removal state, or events without explicit scope.

## Verification

Run the changed domain test, its owning domain directory, architecture tests, PHPStan level 8, and PHPCS. Add persistence checks only when mapping changed.

## Completion report

Report the invariant, state transition, changed domain files, affected mappings, commands run, and skipped checks with exact blockers.
