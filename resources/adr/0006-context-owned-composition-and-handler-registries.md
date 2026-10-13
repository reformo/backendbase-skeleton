# ADR-0006: Context-Owned Composition and Explicit Handler Registries

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A business module must own its adapter bindings and command and query mappings. Global composition must assemble modules without knowing their use cases.

[Context discovery](../../config/dependencies/bounded-contexts.php) loads each service provider. The [CQRS rules](../platform/04-cqrs.md) keep handler imports out of contracts.

- **Constraints:** Every command and query must resolve to one handler. Duplicate mappings must fail. Contracts must remain independent of handlers.

## Considered Options

### Option A: Context service providers with explicit handler registries

- **Pros:** Bindings and handler ownership stay within the module. Dispatch mappings can be checked at composition.
- **Cons / Risks:** Registry entries must follow contract changes. Discovery depends on the module directory convention.
- **Estimated Cost / Effort:** Medium

### Option B: Handler attributes on command and query contracts

- **Pros:** The handler reference is close to its contract. Less separate registry metadata is necessary.
- **Cons / Risks:** Contracts depend on application classes. This weakens module dependency direction.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Explicit registries preserve contract independence and module ownership. The global container only discovers and assembles providers.

## Consequences

- **Positive:** Modules retain their composition when moved. Missing and duplicate handler mappings are detectable.
- **Negative / Trade-offs:** Registry metadata adds maintenance when use cases are added or renamed.

## Implementation & Validation

- **Rollout Steps:** Add mappings to each context provider. Keep the registry resolver as the default in [dependency wiring](../../config/dependencies.php).
- **Verification:** Run registry architecture and container checks. Verify each mapped handler resolves and every command or query has a mapping.
- **Review Date:** Review when module discovery or handler construction requirements change.
