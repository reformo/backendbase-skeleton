# Feature Change Workflow

Start from the user-visible outcome. Add only the layers that the requested behavior needs.

## Mandatory guidance gate

Complete these steps before design or implementation:

1. Read every applicable `AGENTS.md` file.
2. Load the mandatory platform files from `resources/platform/README.md`.
3. Load each task-specific platform file for every affected surface.
4. Open every matching skill and each reference that it requires.
5. State the selected guidance and its implementation constraints.

Repeat this gate when task scope changes. Reassess all earlier design decisions against the new scope before continuing.

Do not substitute local preference for a platform design rule or skill invariant. Report conflicts and request authorization for any deviation.

## Recommended sequence

1. Define or update the public OpenAPI operation when HTTP is affected.
2. Choose or create the owning bounded context.
3. Add a command for a write or a query for a read.
4. Add domain behavior and value objects for business rules.
5. Add narrow persistence or external-service ports.
6. Implement Doctrine or vendor adapters.
7. Implement the application handler.
8. Add a transactional integration event only when required.
9. Add focused domain, handler, adapter, and lifecycle tests.
10. Generate and review a migration only for an approved schema change.
11. Add affected API controllers and routes.
12. Update OpenAPI and Bruno together.
13. Update the `Unreleased` section of `CHANGELOG.md` in the same commit.
14. Run focused tests, PHPStan level 8, cyclomatic complexity, PHPCS, and affected contract checks.

Every changed line must support the requested outcome. Do not refactor adjacent code or add speculative abstractions.

Before completion, map each changed surface to its platform files and skills. Confirm that the result follows every applicable rule.

Report each skipped verification command and its exact blocker.

Basis: `resources/docs/0-project.html` and all topic guides.
