# Backendbase Change Verification Pattern

Use this checklist in another project after mapping its namespaces, source roots, framework, container, test runner, and documentation paths.

## Evidence Order

Use evidence in this order:

1. Target-project agent instructions and explicit user requirements.
2. Current executable code and dependency configuration.
3. Current tests and generated artifacts.
4. Current platform documentation.
5. Older narrative or tutorial documentation.

Backendbase sources for this checklist include:

- `resources/platform/02-architecture.md`
- `resources/platform/04-cqrs.md`
- `resources/platform/05-integration-event-contracts.md`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/09-persistence.md`
- `resources/platform/10-schema-changes.md`
- `resources/platform/11-messaging-outbox.md`
- `resources/platform/12-messaging-consumers.md`
- `resources/platform/14-security.md`
- `resources/platform/15-configuration.md`
- `resources/platform/16-errors-observability.md`
- `resources/platform/17-testing.md`
- `resources/platform/18-deployment.md`
- `resources/docs/project.md`
- `tests/Architecture/`
- `composer.json`
- `phpstan.neon`

The numbered HTML files in `resources/docs/` are useful background. Resolve conflicts in favor of current code, tests, and platform documents.

## Review Matrix

| Changed surface | Required evidence |
| --- | --- |
| Domain behavior | Invariants are enforced before mutation. Invalid and boundary cases have tests. |
| Command or query | One typed message maps to one handler. The current bus can discover it. |
| Port or adapter | Business code depends on the port. Container wiring selects the adapter. |
| Database schema | Forward migration is safe. Runtime mapping, schema, release migration target, and rollback policy agree. |
| HTTP endpoint | Input validation, authorization, response, error, route, OpenAPI, executable example, and focused tests agree in every affected API. |
| Integration event | Event name and version are stable. Producer payload maps to the registered carrier. |
| Queue consumer | ACK, RETRY, and REJECT behavior is explicit. Duplicate delivery is safe. |
| External effect | Timeouts and failure classification exist. Unknown provider results do not cause unsafe retry. |
| Configuration | Type conversion, invalid values, secrets, cache, and worker restart behavior are covered. |
| Console or operation | Inputs, exit codes, idempotency, concurrency, and process supervision are defined. |
| Release tooling | Artifact identity, checksum, migration target, hooks, readiness, and rollback record are tested. |
| Documentation | Architecture, contract, workflow, limitation, path, and operations text matches the change. |

## Architecture Checks

Backendbase tests these rules directly:

- Domain core does not depend on application or infrastructure.
- Business layers do not depend on adapters.
- A bounded context does not import another bounded context directly.
- Business layers do not import framework packages.
- Shared code does not import application services, domain modules, or infrastructure.
- Application code does not import infrastructure.
- Shared core code does not import frameworks.
- Inbound and outbound adapters do not import each other.
- CQRS and domain-listener attributes have one positional same-context target with the correct interface and production-container binding.

Equivalent current tests are:

```text
tests/Architecture/DomainPurityTest.php
tests/Architecture/AdapterDirectionTest.php
tests/Architecture/BoundedContextIsolationTest.php
tests/Architecture/FrameworkImportBoundaryTest.php
tests/Architecture/SharedDependencyBoundaryTest.php
tests/Architecture/ApplicationDependencyBoundaryTest.php
tests/Architecture/SharedCoreFrameworkBoundaryTest.php
tests/Architecture/InboundAdapterDependencyBoundaryTest.php
tests/Architecture/OutboundAdapterDependencyBoundaryTest.php
tests/Architecture/AttributeTargetBoundaryTest.php
```

Also check object-calisthenics or local design constraints from the target project. Do not refactor unrelated code during verification.

Current Backendbase architecture policy deliberately permits `Psr\Log\LoggerInterface` as an application port. It permits CQRS and domain-event contracts to reference one same-context handler or listener through a positional attribute. Confirm the target project's policy before treating either direction as valid.

## Trust-Boundary Checks

Review each untrusted boundary separately:

- HTTP route, query, body, headers, authentication, and authorization.
- Console arguments and options.
- Environment and configuration values.
- Persisted rows and serialized values.
- Queue envelopes and versioned payloads.
- External-service responses and object metadata.

Verify required fields, types, formats, ranges, sizes, and allowed values. Reject invalid input explicitly. Do not silently coerce input unless the public contract requires it.

Verify that logs and public errors exclude credentials, tokens, stack traces, message bodies, and unnecessary personal data.

## Persistence and Messaging Checks

- Validate a write completely before changing state.
- Use one database transaction when several writes form one invariant.
- Write business state and the outbox record in one transaction.
- Keep broker and other network calls outside database transaction callbacks.
- Treat broker delivery as at-least-once.
- Use `(consumer_name, message_id)` or an equivalent stable inbox key.
- Keep published event versions readable while retained messages can still arrive.
- Test actual producer data against the registered carrier or decoder.
- Distinguish permanent invalid input from transient infrastructure failure.
- Use an external-effect inbox when provider calls cannot join the database transaction.
- Do not retry an operation automatically when the provider result is unknown and the operation is not safely idempotent.

## Registration Checks

Code can be correct and still be unreachable. Check every applicable registry:

- PSR-4 autoloading.
- Dependency provider list.
- Context service provider.
- Port-to-adapter binding.
- Command and query handler discovery.
- HTTP use-case target, module, and route map.
- Middleware order.
- Console command definition.
- Event subscriber and carrier registry.
- Queue driver and processor factory.
- Readiness-check collection.
- Doctrine entity path and migration namespace.

For each applicable item, add or run a composition test through the real provider, container, bus, route collection, command list, or registry. Static architecture and direct unit tests do not prove runtime reachability.

## Endpoint verification audit

When application behavior is delivered over HTTP:

1. Enumerate the target project's existing `Infrastructure/UseCase` or equivalent API roots.
2. Identify every existing API affected by the behavior. If a requested API is absent, report the missing surface instead of inventing it.
3. Compare method, path, placeholder names, route name, middleware order, controller validation, command or query, response, status, and errors.
4. Compare runtime security with OpenAPI security. New Backendbase consumer endpoints are protected by default unless the public exception is explicit.
5. Check the four shared Backendbase header contracts: `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`.
6. Check CORS and runtime validation when OpenAPI marks a header required. A specification reference alone does not prove enforcement.
7. Regenerate and validate the specification. Review the generated diff.
8. Update an existing Bruno request whenever its operation contract changed. Add new Bruno coverage only when the target policy or requested scope requires it.

Do not run Bruno against a state-changing or shared target without explicit authority and prepared data.

## Release-manifest parity

When a migration changes:

- Verify the mapped record and repository tests before accepting the generated migration.
- Verify that the release policy points to the exact latest reviewed migration.
- Require an explicit decision for whether the previous application can run against the expanded schema.
- Keep this policy in the release manifest or target-project equivalent.

Backendbase stores these decisions as `migrationTarget` and `applicationRollbackSafe` in `deployment/release.json`. `bin/deployment/build-release.sh` rejects a migration target that is not the latest migration class. Do not infer rollback safety from a reversible Doctrine `down()` method.

## Known Backendbase Drifts

Use these as regression prompts. Do not preserve them as desired behavior:

- `ValidateApiKey` is active in current `ExampleApi/middleware.php`; an older HTML guide says it is commented out.
- `public/index.php` selects an API through `X-Source-Id` and `UseCaseTarget::TARGETS`. Dedicated bootstrap variables are not the current selection contract.
- Some authentication failures return status 400 while the sample OpenAPI declares 401 or 403.
- The sample detail response, pagination rules, and OpenAPI fields are not fully aligned.
- The version 1 producer and registered carrier both preserve the nested `command` shape. Keep the real producer-to-dispatcher contract test passing.
- Internal integration subscribers are registered but are not automatically dispatched.
- SQS `REJECT` relies on external redrive configuration. RabbitMQ retry immediately requeues. Outbox publish retry has no terminal limit.
- Notification provider wiring is incomplete and notification payload logging is unsafe.
- Object-storage multipart retry is unbounded, signed POST omits content type, and downloads force `image/jpeg`.
- `en-US.php` uses the Turkish dictionary.
- Shared object mapping silently drops unknown keys and permits scalar coercion.
- Password hashing input needs stricter boundary review. Pagination rejects non-positive page sizes and overflowing offsets; HTTP boundaries also reject non-positive page numbers.
- The current repository has no metrics exporter, worker supervisor definition, scheduler definition, or automatic dead-letter replay.
- Shared OpenAPI parameters mark four request headers as required, but current runtime does not reject every missing header. The ExampleApi CORS allow-list also omits `Accept-Language`.

## Backendbase CI and verification commands

Adapt commands to the target project. Backendbase uses:

```text
composer validate --strict --no-check-publish
composer audit --locked --no-interaction
vendor/bin/phpunit <smallest relevant test path>
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
composer generate-example-api-spec
composer validate-example-api-spec
vendor/bin/php-openapi validate --silent public/example-api/docs/example-api-merged.yml
git diff --exit-code -- public/example-api/docs/example-api-merged.yml
composer test
bash tests/Deployment/deployment-scripts.sh
```

`phpstan.neon` sets level 8. `composer test` already runs PHPUnit and the deployment shell-script checks, so the explicit shell command is useful only as a focused release-tooling check.

Use `git diff --exit-code` for generated-file parity only on a clean checkout or against a captured pre-generation baseline. In a developer worktree, review `git diff` without treating an intentional uncommitted generated change as automatic failure.

The current workflows have distinct roles:

- `.github/workflows/quality-gates.yml` runs Composer validation and audit, tests, PHPStan, cyclomatic complexity, PHPCS, source OpenAPI validation, generation, and generated-file parity.
- `.github/workflows/security-checks.yml` runs Semgrep and a service-backed OpenAPI dynamic application security test.
- `.github/workflows/release-artifact.yml` repeats release gates and builds an immutable artifact for an exact reviewed revision.

Run only applicable local checks. Do not claim a CI, security, dynamic, or release check passed unless it ran in its required environment. A command that did not run is not evidence.

## Source provenance

- `resources/docs/project.md`
- `resources/platform/02-architecture.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/10-schema-changes.md`
- `resources/platform/17-testing.md`
- `resources/platform/18-deployment.md`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/ModuleRoutes.php`
- `tests/Infrastructure/UseCase/ExampleApi/ModuleRoutingTest.php`
- `resources/api-docs/example-api`
- `resources/bruno/example-api`
- `deployment/release.json`
- `bin/deployment/build-release.sh`
- `composer.json`
- `.github/workflows/quality-gates.yml`
- `.github/workflows/security-checks.yml`
- `.github/workflows/release-artifact.yml`
