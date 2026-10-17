# Backendbase API Project Introduction For Agents

## Purpose

This repository is a PHP 8.5 Slim API project for Backendbase backend services. It follows DDD, Ports and Adapters, and CQRS conventions. Agents should preserve dependency direction: HTTP and infrastructure adapters translate external input into application commands/queries; domain and application code must not depend on Slim, PSR-7, Doctrine implementation details, or concrete infrastructure adapters.

## Technology Summary

- Runtime: PHP 8.5
- HTTP framework: Slim 4
- Dependency injection: PHP-DI
- HTTP messages/responses: Laminas Diactoros
- Persistence: Doctrine ORM and Doctrine DBAL
- Database: MySQL 8.4
- Tests: PHPUnit
- Static analysis and style: PHPStan, cyclomatic complexity, and PHPCS
- API docs: OpenAPI YAML under `resources/api-docs`
- Console commands: Symfony Console-style commands through `bin/backendbase`

## Folder Structure

```text
backendbase-core/
├── bin/
│   ├── backendbase                     # CLI entry point
│   ├── doctrine                       # Doctrine tooling entry point
│   ├── bruno                          # Bruno API end-to-end runner
│   ├── check-coverage.php             # Executable-line coverage threshold
│   ├── check-documentation-links.php  # Local documentation-link validation
│   ├── update-quality-report.php      # Generated quality-report metrics
│   ├── validate-agent-memory.sh       # Local agent-memory structure validation
│   ├── deployment/                    # Deployment scripts
│   ├── dev/                           # Local maintenance scripts
│   └── tolgee/                        # Translation synchronization scripts
├── config/
│   ├── autoload/                      # Shared runtime configuration
│   ├── dependencies/                  # Focused container definition providers
│   ├── example-api/                   # ExampleApi configuration
│   ├── commands.php                   # Console command registration
│   ├── dependencies.php               # Stable container provider loader
│   ├── doctrine-types.php             # Doctrine type registration
│   └── settings.php                   # Settings service registration
├── public/
│   ├── index.php                      # Shared HTTP bootstrap and API selector
│   └── example-api/
│       ├── index.php                  # ExampleApi front controller
│       └── docs/                      # Generated and served ExampleApi specification
├── resources/
│   ├── api-docs/
│   │   ├── common/                    # Shared OpenAPI components
│   │   └── example-api/               # ExampleApi OpenAPI source files
│   ├── bruno/example-api/            # ExampleApi Bruno YAML collection
│   ├── database/
│   │   ├── Migrations/                # Doctrine migration classes
│   │   └── Seeders/                   # Database seeders
│   ├── docs/                          # Project guides and reports
│   ├── docker/cdn.conf.template        # Local Nginx file delivery and cache configuration
│   ├── platform/                      # Platform rules and task routing
│   ├── skills/                        # Reusable task guidance
│   └── i18n/                          # Translation files
├── src/Backendbase/
│   ├── Domain/
│   │   └── ExampleCatalog/     # Current bounded-context reference
│   │   │   ├── Adapters/Persistence/
│   │   │   │   ├── Doctrine/          # Production persistence adapters
│   │   │   │   └── Memory/            # In-memory test adapters
│   │   │   ├── Application/
│   │   │   │   ├── CommandHandlers/
│   │   │   │   ├── QueryHandlers/
│   │   │   │   ├── DomainEventListener/
│   │   │   │   ├── IntegrationEventSubscribers/
│   │   │   │   └── ExternalIntegrationEventSubscribers/
│   │   │   ├── Contracts/
│   │   │   │   ├── Command/
│   │   │   │   ├── Query/
│   │   │   │   ├── ReadModel/
│   │   │   │   ├── DomainEvents/
│   │   │   │   ├── IntegrationEvents/
│   │   │   │   └── ExternalIntegrationEvents/
│   │   │   ├── Domain/                # Aggregates, value objects, and domain enums
│   │   │   ├── Tests/
│   │   │   └── ServiceProvider.php    # Port, handler, and subscriber registration
│   ├── Infrastructure/
│   │   ├── Adapters/                  # Console, HTTP, mapping, persistence, queue, and external adapters
│   │   ├── Configuration/             # Typed runtime, AWS, queue, database, Redis, and logging settings
│   │   ├── Health/                    # Bounded dependency readiness checks
│   │   ├── Messaging/                 # Relay, retry, and delivery-failure coordination
│   │   └── Inbound/                   # Inbound API entry code
│   │       └── ExampleApi/
│   │           ├── Controllers/         # Root and Example HTTP modules
│   │           ├── middleware.php
│   │           └── routes.php
│   └── Shared/
│       ├── Configuration/             # Shared HTTP and configuration validation types
│       ├── CQRS/                      # Command and query buses
│       ├── Domain/                    # Domain events and base domain types
│       ├── Integrations/              # Infrastructure ports
│       ├── Persistence/               # Persistence ports
│       ├── Primitives/                # Shared value objects
│       └── Services/
├── tests/                             # Architecture, domain, infrastructure, and shared tests
├── var/cache/                         # Runtime cache
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── phpcs.xml.dist
└── phpcs-complexity.xml.dist
```

## Architecture Map

### Modular Bounded Context Bundles

Each bounded context is a complete module under `src/Backendbase/Domain/{ContextName}`.

A module keeps its domain, application, contracts, adapters, service provider, and tests together. Module-local tests belong under the module `Tests/` directory. This structure is intentional. It is not an accidental mix of test and production code.

Keep these parts together so a module can be understood, changed, verified, and moved as one unit. Do not move module-local tests to the root `tests/` directory only to follow a conventional source and test separation.

Use the root `tests/` directory for tests that belong to the platform rather than one bounded context. Examples include architecture rules, shared components, infrastructure adapters, and API-level behavior.

### Domain And Application

Bounded contexts live under `src/Backendbase/Domain/{ContextName}`. Use `ExampleCatalog` as the current reference implementation. No `Content` context exists in the current source. Contracts represent command/query inputs and ports. Application handlers orchestrate use cases. Domain objects and services should hold business rules and invariants.

`IdentityAndAccess` owns global Accounts, User and SystemAdmin actors, contact verification, credentials, session checks, and first-Admin setup.
Admin state retains invitation and approval evidence. Operator setup and manual seeding share a database-scoped MySQL advisory lock.
Installation and naming migrations create no separate operator history table.

### Enforced Dependency Boundaries

Architecture tests under `tests/Architecture` parse PHP symbols with `nikic/php-parser`. They enforce these rules:

- Domain core code cannot depend on Application or Infrastructure.
- Application code cannot depend on Infrastructure.
- Technical messaging coordination under `Infrastructure/Messaging` can depend only on its own types, Shared contracts and values, and native PHP types.
- Business layers cannot depend on concrete Adapters.
- A bounded context cannot depend on another bounded context.
- Business layers cannot import HTTP, persistence, messaging, dependency-injection, or vendor framework namespaces.
- Shared code cannot depend on Application, Domain, or Infrastructure.
- No Shared code can import framework namespaces.
- Inbound and outbound adapters cannot depend on each other.
- Every command and query has a resolvable handler mapping in its context registry. Contracts cannot import handlers or declare handler attributes.
- Domain-listener attributes use one positional same-context target that implements the correct interface and resolves from the production container.

`Psr\Log\LoggerInterface` remains allowed as an application port. Command and query contracts do not import their handlers.

`Shared/Time/Clock` supplies UTC instants where time controls messaging availability, retries, claims, age checks, and retention. `config/dependencies/time.php` binds the system adapter. Pure calculations receive an explicit instant. Example audit writes read the injected clock and pass explicit instants to Entry. The date helper retains UTC defaults and explicit time-zone support for other date construction. The Monolog factory sets its own UTC time zone independently.

### Ports And Adapters

Repository ports live in bounded-context `Contracts`. Write ports load and save domain aggregates. Production adapters live under `Adapters/Persistence/Doctrine`. Doctrine records map aggregate state to database columns. Persistence adapters must not implement creation, change, removal, or other domain rules. In-memory test adapters live under `Adapters/Persistence/Memory`. Bind ports to production adapters in the bounded context `ServiceProvider`.

### Inbound APIs

The current repository contains `ExampleApi`, `UserApi`, and `SystemAdminApi` under `src/Backendbase/Infrastructure/Inbound`. Their slugs are `example-api`, `user-api`, and `system-admin-api`.

`X-Source-Id` selects `example`, `user`, or `system-admin` through a fixed target map. The identity APIs use separate trusted authentication realms.

`Infrastructure/Inbound` holds inbound API entry code. Each inbound API keeps its routes, middleware, and controllers together. Operational console commands live under `Infrastructure/Adapters/Console`. Reusable HTTP support stays under `Infrastructure/Adapters/Http`.

Controller modules follow OpenAPI tags through `Controllers/{TagName}/ModuleConfig.php` and `Controllers/{TagName}/Handlers/`. Each operation has one handler and a matching route name.
Use fluent route declarations and `group()` for common middleware. Identity APIs define each group's realm once through `RealmPolicy`.
Protected groups also apply `RealmAuthentication`. Route action and contact policies retain the group realm. Public Authentication groups apply no bearer authentication.
See [HTTP API guidance](../platform/07-http-api.md) for module registration and middleware execution order.

Use `ExampleApi` as the reference for general HTTP adapter patterns. Use the existing identity APIs for realm-specific behavior. Use `resources/skills/backendbase-add-inbound-api/SKILL.md` when the user requests another API. Inspect current directories before assuming another API exists.

Before implementing a feature, inspect the current `Infrastructure/Inbound` directories. Update each existing affected API. If the request requires an API that is not present, confirm whether to scaffold it before adding adapters or documentation.

Apply the target API's existing security policy. Example operations require an API key. Every Example operation except `getExampleByCriteria` also requires a USER bearer token. ExampleApi reuses UserApi contact verification, registration, login, identity, and session services. Identity routes use their trusted USER or ADMIN policy. Define anonymous access explicitly. Internal `QueueGreeting` retains its Shared `AccessControl` check. See [the identity security policy](../platform/14-security.md).

Every new endpoint request must include `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`. Add all four shared header references to each new OpenAPI operation's `parameters` section. Follow `resources/api-docs/example-api/example/examples.yaml`.

### CQRS

Commands and queries implement `Backendbase\Shared\CQRS\Command` or `Backendbase\Shared\CQRS\Query`. They have no handler attributes. The `config/dependencies.php` provider defaults to `RegistryHandlerResolver::class` and uses each context's `ServiceProvider::getHandlers()` mappings. `AttributeHandlerResolver` remains available for separately attributed contracts. Both buses obtain the selected handler from the PHP-DI container.

Command handlers and the command bus return `void`. For a client-visible write result, use either approach:

- Generate the public identifier before a create command or reuse an existing resource's public identity. Carry it in the command and return it only after successful execution.
- For a resource representation, call one application orchestrator in the owning context. It executes the command, waits for commit, then reads through a query or read port. It returns a declared read model or immutable result object for the HTTP action to map.

Keep orchestrator input and result contracts in `Contracts`. Preserve context isolation and authorization for both the command and read. Keep authoritative write lookup and transaction control in the handler or its called application service. Define read consistency and failures after commit. A failed response read does not undo the write and must not cause automatic command repetition. Follow [the client-visible write-result rules](../platform/04-cqrs.md#client-visible-write-results).

### Example API

`ExampleApi` controllers are adapter-layer classes under `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers`. Handlers extend `Backendbase\Infrastructure\Adapters\Http\Actions\Action`, read PSR-7 request data, validate inputs without changing accepted text, create one command or query for the operation, dispatch through the appropriate bus, and return `JsonResponse` or `EmptyResponse`. A write command carries the public resource identity. Its handler resolves authoritative state through a write port.

Example writes read bounded JSON objects through `ExampleRequestBody`. Context values validate identifiers and field limits before persistence.
The API returns stored details without inferred image URLs. See [Example contracts](../platform/08-api-contracts.md) for limits, JSON rules, and compatibility changes.

When adding or changing an endpoint, invoke one application operation in each existing affected API. Direct command or query dispatch is the default. For a write response needing a resource representation, the action can instead call the application orchestrator described above. Example create endpoints use pre-generated identifiers. Identity actions use the context-owned `IdentityOperation` orchestrator for command results.

## Feature Flow

Use the relevant parts of this flow for backend features. Choose the implementation order from actual dependencies. Follow the task-scoped guidance in `resources/platform/README.md`.

```text
OpenAPI endpoint definition
-> bounded context if missing
-> query or command contract
-> domain aggregate, value objects, and rules
-> repository ports
-> Doctrine mapping record with attributes if missing
-> Doctrine persistence adapters if missing
-> query or command handler
-> application orchestration
-> integration event contract and transactional outbox write when requested
-> internal or external integration event subscriber when requested
-> PHPUnit tests using the Doctrine repository
-> Doctrine migration diff when entities changed, after any Memory tests pass and Doctrine repositories are finalized
-> dry-run the reviewed migration against an identified, prepared database
-> apply only with explicit authority for that database, then verify its schema
-> affected inbound API controllers and route registration after tests pass
-> Bruno YAML E2E tests under resources/bruno when API E2E coverage is requested
```

For new query endpoints, use Doctrine DBAL and plain SQL for read-side projections. Keep DBAL and SQL in a persistence/read adapter, not in Slim controllers or domain objects. For writes, let command handlers resolve aggregates through write repository ports and call domain behavior. Do not query a read model in a controller to resolve a write target. Let Doctrine adapters map, load, and save aggregate state only.

Create ORM repository test schemas with Doctrine `SchemaTool` and the production mapping metadata. Do not copy mapped tables into handwritten test DDL.

When creating Doctrine mapping records, enum fields must use PHP native string-backed enum classes and Doctrine `enumType` column mapping. Type the record property with the enum class. Follow `EntryType` and `ExampleTable::$type`, which use `Types::ENUM` with `enumType: EntryType::class`.

Generate a migration only for an explicitly approved schema change. Run `bin/doctrine migrations:diff` from the repository root after repository tests pass and repository behavior is final. Generated migrations live under `resources/database/Migrations`. Review every generated SQL statement. Use plain `CREATE TABLE` so an unexpected existing table stops the migration. Use `IF NOT EXISTS` only with an explicit adoption plan and exact schema validation.

Identify the target database and inspect its pending migrations. Run `bin/doctrine migrations:migrate --dry-run --no-interaction` against that prepared target. Apply with `bin/doctrine migrations:migrate --no-interaction` only with explicit authority for that database and every pending statement. Feature implementation approval does not authorize database mutation. Use the manifest-backed deployment workflow for production. Report an unapproved or unavailable target as skipped verification. Do not apply a migration only to complete a coding task.

Database change boundary (hard rule): never create, alter, or drop any database table, column, index, or schema on your own initiative. Build ONLY the exact structure the user explicitly requested — no extra tables and no extra columns, not even "obvious" ones like timestamps, soft-delete, status, or audit fields, unless the user asked for them. If a feature seems to need a table or column the user did not mention, STOP and ask before creating it. When reviewing a generated `migrations:diff`, if it contains anything the user did not request, do not run `migrations:migrate` — report it and ask. The database structure is the user's decision, not yours.

Command handlers may create integration events. Add producer event contracts under `Contracts/IntegrationEvents`. Use `IntegrationEventTransaction::execute()` to commit business work, local subscriber writes, and optional outbox publication in one transaction. Perform authoritative database work in its callback and return the complete event. The transaction wrapper calls `EventManager::dispatchEvent()` before commit. Run synchronous domain listeners inside the transaction callback when their failure must roll back the command. Keep business-relevant external effects asynchronous through the outbox. Do not publish queue messages or call `EventManager::dispatchEvent()` directly from a command handler.

New integration event types must follow `{PascalCaseServiceName}_{PascalCaseEventClassName}`. Read the service name from the `service-name` key in `config/autoload/global.php`. The current default is `example`. The existing `EntryChanged` producer preserves its published `Example_ExampleChanged` name. Existing published names are compatibility contracts. Do not rename them without a migration plan.

Both `DELIVER_VIA_QUEUE` values run registered local integration subscribers synchronously. A true flag appends one outbox row after local subscribers complete, even when no local subscriber is registered. A false flag produces no outbox row. Producer dispatch requires an active database transaction. Subscriber repositories and `DoctrineIntegrationEventOutbox` must share its connection. Subscriber and outbox failures roll back the database work. The outbox relay publishes committed rows through the configured `MessagePublisher` adapter. Queue consumption uses `dispatchExternalEvent()` and does not republish the received event.

Internal subscribers implement `IntegrationEventSubscriber` and live under `Application/IntegrationEventSubscribers`. External subscribers implement `ExternalIntegrationEventSubscriber` and live under `Application/ExternalIntegrationEventSubscribers/{SourceService}`. Versioned external message carriers live under `Contracts/ExternalIntegrationEvents/{Version}`. Register both subscriber types in the bounded context `ServiceProvider`.

The queue message processor converts a producer event name to an external subscriber name by adding `_Event`. For example, `Example_NewExampleAdded` becomes `Example_NewExampleAdded_Event`. Keep the subscriber constant, registry entry, event version, and message carrier aligned.

## Important Entry Points

- HTTP bootstrap: `public/index.php`
- ExampleApi front controller: `public/example-api/index.php`
- ExampleApi middleware: `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- ExampleApi routes: `src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php`
- ExampleApi liveness and readiness actions: `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Root`
- Dependency readiness checks: `src/Backendbase/Infrastructure/Health`
- ExampleApi module registry: `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/ModuleRoutes.php`
- ExampleApi configuration: `config/example-api`
- ExampleApi OpenAPI source: `resources/api-docs/example-api`
- ExampleApi Bruno collection: `resources/bruno/example-api`
- Identity API adapters: `src/Backendbase/Infrastructure/Inbound/UserApi` and `src/Backendbase/Infrastructure/Inbound/SystemAdminApi`
- Identity OpenAPI sources: `resources/api-docs/user-api` and `resources/api-docs/system-admin-api`
- Identity Bruno collections: `resources/bruno/user-api` and `resources/bruno/system-admin-api`
- Bruno HTML reports: `artifacts/bruno/{collection}/{environment}.html`
- Reference bounded context: `src/Backendbase/Domain/ExampleCatalog`
- Doctrine migrations: `resources/database/Migrations`
- Main DI wiring: `config/dependencies.php`
- Focused DI providers: `config/dependencies`
- Console command wiring: `config/commands.php`
- PHPUnit suite config: `phpunit.xml`
- Composer scripts: `composer.json`
- Composer supply-chain verifier: `bin/composer-supply-chain.php`
- Composer SBOM and reviewed package digests: `resources/security`

## Agent Skills In This Repository

Reusable skills live under `resources/skills`. Use the [platform router](../platform/README.md) to select a skill for the affected behavior. Follow [the skill-authoring rules](../skills/AGENTS.md) when changing a skill.

Use [naming-things](../skills/naming-things/SKILL.md) to name or rename code, files, tests, and agent artifacts. Preserve project vocabulary, naming conventions, behavior, and public contracts.

Use `backendbase-implement-feature` for coordinated changes across layers. Use `backendbase-verify-change` for a requested cross-layer review or when the change risk needs that review.

Select additional skills only for required procedures or invariants not already covered. Read only relevant reference sections. Reuse verified discovery and successful checks for unchanged inputs. Individual skills retain these rules when copied to another project. A prose-only correction needs no implementation skill.

The root `AGENTS.md` defines authorization and completion. Existing explicit authority covers the same operation, target, and scope. Local code changes do not authorize database application, deployment, or remote synchronization.

Use the [context routing table](../platform/README.md#context-instructions) before changing a context or its related API, configuration, database, or tests.
Read only the affected context instructions. Run project commands from the repository root.
Each context owns a short `AGENTS.md` shared by Codex and Claude Code.
Update these instructions when context rules change. Keep detailed guidance and architecture decisions in their existing documents.

## Codex Agent Workflow

Use this workflow only when the user explicitly requests it. The main agent works alone for ordinary tasks.

Project model defaults live in [.codex/config.toml](../../.codex/config.toml).
The main agent uses `gpt-6.1-sol` with `high` reasoning effort.
All five custom agents use `gpt-6.1-sol`.
The `planner` and `implementer` request Fast mode with `xhigh` reasoning effort.
The `test_analyst` requests Fast mode with `high` reasoning effort.
The `plan_reviewer` and `code_reviewer` use standard speed with `max` reasoning effort.
Five [custom agent definitions](../../.codex/agents) supply planning, test analysis, plan review, implementation, and code review.
The [Agent Workflow procedure](../platform/agent-workflow.md) defines their order, review decisions, and correction rules.

The main agent coordinates every stage.
The `test_analyst` defines test scope before implementation and audits test sources and execution evidence afterward.
After implementation, `code_reviewer` and the test audit can run in parallel against the same code state.
The main agent combines required findings before requesting corrections.
Each custom agent disables further delegation.
The `implementer` uses `workspace-write` mode. The other custom agents use `read-only` mode.

Start a new Codex session after changing these files.
Codex loads project configuration for trusted projects.
Explicit session settings can override project model defaults.
Startup instruction discovery follows the repository-root-to-working-directory path. Root-started tasks explicitly read affected context instructions through the shared routing table.
See [Codex instruction discovery](https://learn.chatgpt.com/docs/agent-configuration/agents-md).

## Claude Code Agent Workflow

Project defaults live in [.claude/settings.json](../../.claude/settings.json).
The main agent uses the `sonnet` alias with `high` effort.
Five [Claude agent definitions](../../.claude/agents) preserve the Codex roles, prompts, and review decisions.
Claude Code reads the [root instructions](../../AGENTS.md) and selected context `AGENTS.md` files directly.
Both clients use the [same workflow](../platform/agent-workflow.md) only when the user requests it.
The root file links to the procedure without importing it at startup.
Related API or database work also selects context instructions through the shared routing table.
This repository does not use `CLAUDE.md` files.

Direct loading requires Claude Code 2.1.277 or later with built-in `AGENTS.md` support enabled.
Use 2.1.281 or later for sessions affected by earlier provider or telemetry restrictions.
The default instruction mode loads `AGENTS.md` when no ancestor `CLAUDE.md` or `CLAUDE.local.md` takes precedence.
If client settings prevent loading, enable a supported `AGENTS.md` instruction mode in the client. Do not create instruction copies.
See [Claude AGENTS.md support](https://code.claude.com/docs/en/memory#agents-md).

Sonnet supplies planning, test analysis, and implementation. Opus supplies plan and code review.
The aliases resolve according to the installed client and provider.
This mapping follows the responsibilities of each role.

The four analysis roles use read tools and `plan` permission mode.
The `implementer` receives editing and shell tools and inherits session permissions.
All five tool lists exclude delegation tools.

The Sonnet planner uses normal mode.
Claude Fast mode is a session setting for supported Opus models.
The subagent format has no Fast mode field. See [Claude Fast mode](https://code.claude.com/docs/en/fast-mode).

The project sets `CLAUDE_CODE_MAX_CONCURRENT_SUBAGENTS` to `5`.
Native enforcement requires Claude Code 2.1.217 or later.
The main agent also enforces the five-agent limit in project instructions.
See [Claude subagent limits](https://code.claude.com/docs/en/sub-agents#concurrent-subagent-limit).

Start a new Claude Code session after creating these files.
Ask Claude to list project subagents and confirm the five configured names.
Model execution and account access require a live session.

## Commands

Prefer project scripts when dependencies are installed:

```sh
composer run start-apis
composer test
composer phpstan
composer complexity
composer cs-check
composer docs:check-links
composer reports:check
composer generate-example-api-spec
composer generate-user-api-spec
composer generate-system-admin-api-spec
composer validate-identity-api-specs
bin/doctrine migrations:status
bin/backendbase
```

Migration generation and application require the schema scope and target approval described above.

Use targeted PHPUnit paths first when changing a bounded context:

```sh
vendor/bin/phpunit src/Backendbase/Domain/ExampleCatalog/Tests
```

## Current Assessment

The [engineering assessment](code-quality-and-maintainability-report.html) and [architecture assessment](ports-and-adapters-critique.html) record the completed working tree based on `df29f9c` on 16 October 2026. Code quality is reassessed from 8/10 to 9.2/10. The report gives ten area scores, twenty criterion scores, deductions, and the calculation.

- Preserve context-owned ports, registry mappings, transaction completion, failure-path tests, and API contract parity.
- All adapters have explicit ownership. Unknown categories and forbidden dependency fixtures fail architecture checks.
- Account and Actor now own login and session identity decisions. The selected forwarding layers are simplified. Other workflow snapshots remain a local design cost.
- Live HTTP checks cover all 28 identity operations. Both realms have authenticated scans of protected reads and metadata. Semgrep inputs are pinned.
- Dependency integrity passed for all 141 packages in a fresh isolated install. Local AWS pruning and PHPCS configuration explain the working-installation differences.
- The current release policy retains unsafe application rollback. Guarded fresh installation supports an empty database during maintenance. Normal update guards remain active.

The assessment suite passed 998 tests and 22,653 assertions with no skips and 100% executable-line coverage. Its static, API, dependency, security, and deployment results retain that dated scope. Production acceptance remains open.

The later isolated service run uses the working tree based on `726e8de`. It passes 1,160 tests and 30,476 assertions with no failures, errors, or skips. All 55 previously skipped cases pass on dedicated MySQL 8.4.11, Redis 8.10.2, and Sentinel fixtures. All 6,306 executable lines and 1,497 methods are covered. The coverage gate, isolated deployment fixtures, PHPStan level 8, complexity, and PHPCS pass. HTTP regressions close the earlier gap in seven identity handlers. See the [latest verification evidence](code-quality-and-maintainability-report.html#evidence).

## Agent Working Notes

- Use the real namespace and path `src/Backendbase/Infrastructure`.
- Treat `src/Backendbase/Domain/ExampleCatalog` as the bounded-context reference.
- Name new contexts after the target business capability. Omit `Context` and `BoundedContext` suffixes. `ExampleCatalog` is a demonstration name, and its domain entity is `Entry`.
- Treat `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example` as the HTTP module reference.
- The current repository contains `ExampleApi`, `UserApi`, and `SystemAdminApi`. Inspect all affected APIs before changing a public contract.
- Update `resources/api-docs/example-api` and `resources/bruno/example-api` when a public ExampleApi route changes.
- Update the `Unreleased` section of `CHANGELOG.md` in every commit.
- Use targeted tests under `tests/Infrastructure/Inbound/ExampleApi` for HTTP adapter behavior.
- Report required checks that could not run, with exact commands and blockers. Omit command examples unrelated to the changed behavior.
