---
name: backendbase-implement-feature
description: Coordinate an end-to-end Backendbase-style feature across several architectural surfaces, such as domain behavior, CQRS, persistence, HTTP, messaging, configuration, and tests. Use when one request requires multiple artifacts and their contracts to remain aligned. Do not use for a single command, endpoint, migration, consumer, or other one-surface change; use the narrower skill instead.
---

# Implement a Cross-Layer Feature

Use this skill as an orchestrator. Keep each feature slice small and select only the specialized skills that the request needs.

## Start With Evidence

1. Read the target project's agent instructions.
2. Inspect Composer autoloading, namespaces, source roots, container wiring, tests, and documentation layout.
3. Discover the current HTTP APIs, console entry points, consumers, and other delivery surfaces.
4. Mark each discovered surface as affected or not applicable for the requested behavior.
5. Find the nearest working example for each required surface.
6. Read [the Backendbase feature pattern](references/backendbase-pattern.md).
7. State assumptions, the requested behavior, exclusions, and verifiable success criteria.

Treat the reference as architectural evidence. Adapt names and paths to the target project. Never copy `Backendbase\`, `Example`, credentials, hosts, fixture identifiers, or environment values into another project.

Use a reference API only as a pattern. Update every existing affected API. If an expected API, public root, configuration tree, or contract tree is absent, report the gap before deciding to scaffold or skip it.

## Route the Work

Use a narrower `backendbase-*` skill for every artifact when it is available:

- Domain: bounded context, behavior, value object, command, query, domain event, or problem.
- Persistence: write adapter, read adapter, memory adapter, migration, or seeder.
- HTTP: use-case API, module, endpoint, middleware, security, OpenAPI operation, or Bruno test.
- Messaging: producer, contract evolution, subscriber, processor, consumer runtime, driver, or operations.
- Platform: configuration, readiness, logging, external service, storage, notification, i18n, primitives, mapping, or release.
- Verification: domain tests, architecture rules, and cross-layer change verification.

If the request needs only one artifact, stop using this orchestrator and use the matching narrow skill.

## Build the Feature

1. Define the requested business rule and its public contract. Draft the OpenAPI operation for every affected API when HTTP is in scope.
2. Choose or create the owning bounded context. Add the command or query and the required domain behavior and typed values.
3. Define narrow project-owned ports. Implement only the required persistence, messaging, or external-service adapters.
4. Add the application handler. Add asynchronous publication or consumption only when the feature requires it.
5. Register each adapter, handler, subscriber, and other runtime entry in the target composition root.
6. Run focused domain, contract, handler, adapter, provider, and lifecycle tests for the changed surfaces.
7. If an approved mapping change exists, generate the migration diff only after repository behavior is final. Review every statement and remove unrelated changes.
8. Dry-run and apply the reviewed migration only against an explicitly approved target database. A code request alone does not authorize database mutation.
9. Add HTTP or console delivery adapters after the application path and focused tests are complete.
10. Reconcile runtime input, output, errors, and authorization with OpenAPI. Update executable API examples such as Bruno when the target maintains them.
11. Update operations guidance, release migration metadata, and architecture documentation when those surfaces changed.

Keep domain and application code independent from frameworks and infrastructure. Validate untrusted data at HTTP, console, configuration, storage, and messaging boundaries. Convert valid data into typed objects before it reaches business logic.

Do not create schema or seed data because it appears useful. Confirm every table, column, index, constraint, and reference row. Route approved seed data through the seeder workflow.

## Keep Contracts Aligned

Trace each field through all participating surfaces:

`input -> typed contract -> handler -> model/port -> adapter -> output/event -> documented schema -> tests`

Verify names, types, required state, nullability, defaults, version, and error behavior at every step. Add a producer-to-carrier mapping test for each external event. Do not claim exactly-once delivery.

## Verify in Layers

1. Run the smallest tests for each changed artifact.
2. Run the complete feature or bounded-context test set.
3. Run architecture tests.
4. Run PHPStan at the target project's required level.
5. Run the style check.
6. Validate generated API contracts when HTTP behavior changes.
7. Run the full suite when risk or project policy requires it.

Do not execute a migration, deploy a release, replace tracked localization files, or mutate an external system without explicit authorization.

## Report Completion

Report the behavior delivered, the contracts kept in sync, the files changed, and the exact verification results. State any known limitation or unverified external dependency.
