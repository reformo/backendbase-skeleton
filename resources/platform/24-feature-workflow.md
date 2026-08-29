# Feature Change Workflow

Start from the user-visible outcome. Add only the layers that the requested behavior needs.

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

Report each skipped verification command and its exact blocker.

Basis: `resources/docs/0-project.html` and all topic guides.
