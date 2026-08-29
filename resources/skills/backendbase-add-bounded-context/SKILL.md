---
name: backendbase-add-bounded-context
description: Create a new Backendbase-style bounded context with its domain boundary, contracts, required adapters, service provider, and owned tests. Use when a new business capability needs a module; do not use for adding one feature to an existing context or for scaffolding an HTTP API.
---

# Add a Backendbase Bounded Context

## Outcome

Create the smallest movable module that owns one business language and integrates with the target project without crossing architecture boundaries.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, the root namespace, domain layout, provider discovery, container wiring, PHPUnit test roots, and the nearest composition test.
3. Find the nearest complete bounded context. Reject empty modules and partial legacy modules as references.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. Confirm the requested capability, required ports, persistence, events, and schema scope. Do not infer any of them.

## Target-project adaptation

Preserve the target project's namespace and established naming. Use Backendbase sources as decision references, not literal templates. Do not copy `Backendbase\`, `Example`, table names, columns, configuration, or environment values unless the target project already owns them.

## Workflow

1. Name one PascalCase context for one business capability.
2. Create only the required `Domain`, `Contracts`, `Application`, `Adapters`, and `Tests` areas.
3. Put business state and rules in `Domain`.
4. Put commands, queries, ports, read models, and event contracts in `Contracts` only when requested behavior needs them.
5. Put use-case orchestration in `Application` and technology mapping in `Adapters`.
6. Add the root `ServiceProvider.php`. Bind only ports owned by this context and register only requested subscribers.
7. Add movable context tests under the context-owned test root. Add platform, Shared, infrastructure, API, functional, or architecture tests under the target project's corresponding root.
8. Add a composition test that loads the real provider-discovery path, resolves every port binding, and validates requested subscriber metadata.
9. Update affected platform documentation when the module changes documented behavior.

## Backendbase invariants

- Keep the context as a direct child of `src/Backendbase/Domain` in an unmodified Backendbase project.
- Keep HTTP adapters under `Infrastructure/UseCase`, outside the context.
- Do not import another bounded context or its adapters.
- Do not import frameworks into business layers.
- Implement `Backendbase\Shared\ServiceProvider` at the exact discovered provider path.
- The current Backendbase repository has no `Content` context. Use `ExampleBoundedContext` as the complete provider-based reference; do not infer a module from absent or partial paths.
- Do not add Composer mapping for a normal context covered by the root PSR-4 mapping.
- Run discovery-dependent commands from the repository root.
- Do not create a table, column, index, timestamp, event, or adapter without explicit feature scope.

## Verification

Run the new context's focused tests, its real provider composition test, the architecture suite, PHPStan level 8, the configured complexity check, and PHPCS. Run Doctrine schema validation only when the context has mapped records and a prepared database.

## Completion report

Report the capability boundary, created paths, port bindings, subscriber registrations, documentation changes, commands run, and each skipped check with its blocker.
