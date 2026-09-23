# Backendbase Change Verification Pattern

Use relevant sections after resolving unknown target namespaces, source roots, framework, container, test runner, and documentation paths. Reuse established facts from unchanged source. Source lists record provenance; they are not mandatory reading lists.

## Evidence Order

Target-project instructions, explicit user requirements, and mandatory platform design rules define the required behavior. Current code cannot authorize a deviation from those rules.

For current-state claims, use evidence in this order:

1. Current executable code and dependency configuration.
2. Current tests and generated artifacts.
3. Current platform documentation.
4. Older narrative or tutorial documentation.

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

The numbered HTML files in `resources/docs/` are background for unresolved details. Use current code and tests to establish behavior. Report a conflict with a mandatory design rule before choosing a deviation.

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
- No Shared code imports frameworks.
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
tests/Architecture/SharedFrameworkBoundaryTest.php
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

For changed registration, run a composition test through the real provider, container, bus, route collection, command list, or registry. Add a test when required evidence is missing and implementation is authorized. Static architecture and direct unit tests do not prove runtime reachability.

## Endpoint verification audit

When application behavior is delivered over HTTP:

1. Enumerate the target project's existing `Infrastructure/Inbound` or equivalent API roots.
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

- `ValidateApiKey` is active in `ExampleApi/middleware.php`. Verify API-key and bearer policy independently.
- `public/index.php` selects an API through `X-Source-Id` and `ConsumerApiTarget::TARGETS`. Dedicated bootstrap variables are not the current selection contract.
- API-key and bearer failures return `401`. Named privilege denials return `403`. Invalid time-zone headers return `400`.
- Example groups use a complete total and the requested page. Detail fields match the projection, but their OpenAPI timestamp format remains `datetime`.
- The version 1 producer and registered carrier both preserve the nested `command` shape. Keep the real producer-to-dispatcher contract test passing.
- Producer transactions dispatch local integration subscribers before commit. Verify both delivery flag values and rollback when a local subscriber fails.
- SQS `REJECT` relies on external redrive configuration. RabbitMQ retry immediately requeues. Outbox publish retry has no terminal limit.
- SNS SMS and SES email are registered by default. Twilio, Netgsm, SMTP, and configured Firebase are available. No notification queue contract or consumer is registered.
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

Select commands by changed behavior and target policy. Do not run this entire list for every review. Reuse successful checks while relevant inputs and the environment remain unchanged. Document-only changes need document checks.

Do not claim a CI, security, dynamic, or release check passed unless it ran in its required environment. A command that did not run is not evidence.

## Source provenance

- `resources/docs/project.md`
- `resources/platform/02-architecture.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/10-schema-changes.md`
- `resources/platform/17-testing.md`
- `resources/platform/18-deployment.md`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/ModuleRoutes.php`
- `tests/Infrastructure/Inbound/ExampleApi/ModuleRoutingTest.php`
- `resources/api-docs/example-api`
- `resources/bruno/example-api`
- `deployment/release.json`
- `bin/deployment/build-release.sh`
- `composer.json`
- `.github/workflows/quality-gates.yml`
- `.github/workflows/security-checks.yml`
- `.github/workflows/release-artifact.yml`
