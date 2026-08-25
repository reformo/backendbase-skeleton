# Backendbase API Project Introduction For Agents

## Purpose

This repository is a PHP 8.5 Slim API project for Backendbase backend services. It follows DDD, Ports and Adapters, and CQRS conventions. Agents should preserve dependency direction: HTTP and infrastructure adapters translate external input into application commands/queries; domain and application code must not depend on Slim, PSR-7, Doctrine implementation details, or concrete infrastructure adapters.

## Technology Summary

- Runtime: PHP 8.5
- HTTP framework: Slim 4
- Dependency injection: PHP-DI
- HTTP messages/responses: Laminas Diactoros
- Persistence: Doctrine ORM and Doctrine DBAL
- Database: MySQL 8
- Tests: PHPUnit
- Static analysis and style: PHPStan and PHPCS
- API docs: OpenAPI YAML under `resources/api-docs`
- Console commands: Symfony Console-style commands through `bin/backendbase`

## Folder Structure

```text
backendbase-core/
├── bin/
│   ├── backendbase                     # CLI entry point
│   ├── doctrine                       # Doctrine tooling entry point
│   ├── bruno                          # Bruno API end-to-end runner
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
│   ├── bruno/example-api/            # ExampleApi end-to-end requests
│   ├── database/
│   │   ├── Migrations/                # Doctrine migration classes
│   │   └── Seeders/                   # Database seeders
│   ├── docs/                          # Project guide and local skills
│   └── i18n/                          # Translation files
├── src/Backendbase/
│   ├── Domain/
│   │   ├── Content/                   # Empty placeholder; not a reference implementation
│   │   ├── ExampleBoundedContext/     # Current bounded-context reference
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
│   │   │   └── ServiceProvider.php
│   │   └── IdentityAndAccess/         # Authentication and authorization context
│   ├── Infrastructure/
│   │   ├── Adapters/                  # Notification, persistence, queue, and object-store adapters
│   │   ├── Health/                    # Bounded dependency readiness checks
│   │   └── UseCase/
│   │       ├── ExampleApi/
│   │       │   ├── Controllers/Example/  # Example HTTP module
│   │       │   ├── middleware.php
│   │       │   └── routes.php
│   │       └── Console/
│   │           ├── GoodHousekeeping/
│   │           └── Queue/
│   └── Shared/
│       ├── CQRS/                      # Command and query buses
│       ├── Domain/                    # Domain events and base domain types
│       ├── Http/                      # HTTP actions, middleware, and error handling
│       ├── Integrations/              # Infrastructure ports
│       ├── Persistence/               # Persistence ports and shared Doctrine support
│       ├── Primitives/                # Shared value objects
│       └── Services/
├── tests/                             # Architecture, domain, infrastructure, and shared tests
├── var/cache/                         # Runtime cache
├── composer.json
├── phpunit.xml
├── phpstan.neon
└── phpcs.xml.dist
```

## Architecture Map

### Modular Bounded Context Bundles

Each bounded context is a complete module under `src/Backendbase/Domain/{ContextName}`.

A module keeps its domain, application, contracts, adapters, service provider, and tests together. Module-local tests belong under the module `Tests/` directory. This structure is intentional. It is not an accidental mix of test and production code.

Keep these parts together so a module can be understood, changed, verified, and moved as one unit. Do not move module-local tests to the root `tests/` directory only to follow a conventional source and test separation.

Use the root `tests/` directory for tests that belong to the platform rather than one bounded context. Examples include architecture rules, shared components, infrastructure adapters, and API-level behavior.

### Domain And Application

Bounded contexts live under `src/Backendbase/Domain/{ContextName}`. Use `ExampleBoundedContext` as the current reference implementation. The `Content` directory is empty and must not be used as a reference. Contracts represent command/query inputs and ports. Application handlers orchestrate use cases. Domain objects and services should hold business rules and invariants.

### Enforced Dependency Boundaries

Architecture tests under `tests/Architecture` parse PHP symbols with `nikic/php-parser`. They enforce these rules:

- Domain core code cannot depend on Application or Infrastructure.
- Business layers cannot depend on concrete Adapters.
- A bounded context cannot depend on another bounded context.
- Business layers cannot import HTTP, persistence, messaging, dependency-injection, or vendor framework namespaces.
- Shared code cannot depend on Domain or Infrastructure.

`Psr\Log\LoggerInterface` remains allowed as an application port. Command and query contracts can reference their handlers through `CQRSHandler` attributes.

### Ports And Adapters

Repository ports live in bounded-context `Contracts`. Write ports load and save domain aggregates. Production adapters live under `Adapters/Persistence/Doctrine`. Doctrine records map aggregate state to database columns. Persistence adapters must not implement creation, change, removal, or other domain rules. In-memory test adapters live under `Adapters/Persistence/Memory`. Bind ports to production adapters in the bounded context `ServiceProvider`.

### Consumer API Use Cases

The current repository contains one HTTP API: `ExampleApi`. Its HTTP adapter lives under `src/Backendbase/Infrastructure/UseCase/ExampleApi`. Its public, configuration, OpenAPI, and Bruno roots use the `example-api` slug.

Use `ExampleApi` as the reference implementation for controllers, routes, middleware, OpenAPI, and Bruno end-to-end patterns. Use `resources/docs/add-api` when the user requests another API. Do not assume that `UserApi`, `ExpertApi`, `AdminApi`, or `B2BApi` exists.

Before implementing a feature, inspect the current `Infrastructure/UseCase` directories. Update each existing affected API. If the request requires an API that is not present, confirm whether to scaffold it before adding adapters or documentation.

All new consumer API endpoints must be grouped with `AuthorizationMiddleware` by default. Leave a new endpoint ungrouped only when the request strictly states that authorization is not required, or when it is clearly a public/common endpoint such as registration/authentication bootstrap, callback/webhook handling, or non-user-related public information. If it is not clear whether a public exception applies, ask before implementing the route.

Every new endpoint request must include `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`. Add all four shared header references to each new OpenAPI operation's `parameters` section. Follow `resources/api-docs/example-api/example/examples.yaml`.

### CQRS

Commands and queries implement `Backendbase\Shared\CQRS\Command` or `Backendbase\Shared\CQRS\Query`. Each contract uses `#[CQRSHandler(HandlerClass::class)]`. `ContainerAwareCommandBus` and `ContainerAwareQueryBus` resolve handlers from that attribute and the PHP-DI container.

### Example API

`ExampleApi` controllers are adapter-layer classes under `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers`. Handlers extend `Backendbase\Shared\Http\Actions\Action`, read PSR-7 request data, sanitize inputs, create commands or queries, dispatch through the appropriate bus, and return `JsonResponse` or `EmptyResponse`.

When adding or changing an endpoint, use this adapter shape in each existing affected API. The current repository has only `ExampleApi`.

## Feature Flow

Use this flow for backend features:

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
-> migrate generated Doctrine migration and verify it executes DB changes
-> affected consumer API controllers and route registration after tests pass
-> Bruno YAML E2E tests under resources/bruno when API E2E coverage is requested
```

For new query endpoints, use Doctrine DBAL and plain SQL for read-side projections. Keep DBAL and SQL in a persistence/read adapter, not in Slim controllers or domain objects. For writes, let command handlers call behavior on domain aggregates. Pass aggregates through write repository ports. Let Doctrine adapters map, load, and save aggregate state only.

Create ORM repository test schemas with Doctrine `SchemaTool` and the production mapping metadata. Do not copy mapped tables into handwritten test DDL.

When creating Doctrine mapping records, enum fields must use PHP native string-backed enum classes and Doctrine `enumType` column mapping. Type the record property with the enum class. Follow `ExampleType` and `ExampleRecord::$type`, which use `Types::ENUM` with `enumType: ExampleType::class`.

When a feature creates or updates Doctrine entities, run `bin/doctrine migrations:diff` from the `backendbase-core` project root after repository tests pass and the Doctrine repository implementation is final. Generated migrations live under `resources/database/Migrations`. Review each generated migration and run `bin/doctrine migrations:migrate` before completion. If either command cannot run, report the exact command and failure. Every generated table-creation statement must use `CREATE TABLE IF NOT EXISTS`.

Database change boundary (hard rule): never create, alter, or drop any database table, column, index, or schema on your own initiative. Build ONLY the exact structure the user explicitly requested — no extra tables and no extra columns, not even "obvious" ones like timestamps, soft-delete, status, or audit fields, unless the user asked for them. If a feature seems to need a table or column the user did not mention, STOP and ask before creating it. When reviewing a generated `migrations:diff`, if it contains anything the user did not request, do not run `migrations:migrate` — report it and ask. The database structure is the user's decision, not yours.

Command handlers may create integration events. Add producer event contracts under `Contracts/IntegrationEvents`. Use `IntegrationEventTransaction::execute()` to store the database mutation and outbox message in one transaction. Run synchronous domain listeners inside the transaction callback when their failure must roll back the command. Keep business-relevant external effects asynchronous through the outbox. Do not publish queue messages or call `EventManager::dispatchEvent()` directly from a command handler.

New integration event types must follow `{PascalCaseServiceName}_{PascalCaseEventClassName}`. Read the service name from the `service-name` key in `config/autoload/global.php`. The current default is `example`, so a new `ExampleChanged` event uses `Example_ExampleChanged`. Existing published names are compatibility contracts. Do not rename them without a migration plan.

Producer integration events that enter the outbox declare `IS_MESSAGING_EVENT = true`. The outbox relay publishes them through the configured `BackendbaseQueue` adapter.

Internal subscribers implement `IntegrationEventSubscriber` and live under `Application/IntegrationEventSubscribers`. External subscribers implement `ExternalIntegrationEventSubscriber` and live under `Application/ExternalIntegrationEventSubscribers/{SourceService}`. Versioned external message carriers live under `Contracts/ExternalIntegrationEvents/{Version}`. Register both subscriber types in the bounded context `ServiceProvider`.

The queue message processor converts a producer event name to an external subscriber name by adding `_Event`. For example, `Example_NewExampleAdded` becomes `Example_NewExampleAdded_Event`. Keep the subscriber constant, registry entry, event version, and message carrier aligned.

## Important Entry Points

- HTTP bootstrap: `public/index.php`
- ExampleApi front controller: `public/example-api/index.php`
- ExampleApi middleware: `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- ExampleApi routes: `src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php`
- ExampleApi liveness and readiness actions: `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Root`
- Dependency readiness checks: `src/Backendbase/Infrastructure/Health`
- ExampleApi module registry: `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/ModuleRoutes.php`
- ExampleApi configuration: `config/example-api`
- ExampleApi OpenAPI source: `resources/api-docs/example-api`
- ExampleApi Bruno collection: `resources/bruno/example-api`
- Reference bounded context: `src/Backendbase/Domain/ExampleBoundedContext`
- Doctrine migrations: `resources/database/Migrations`
- Main DI wiring: `config/dependencies.php`
- Focused DI providers: `config/dependencies`
- Console command wiring: `config/commands.php`
- PHPUnit suite config: `phpunit.xml`
- Composer scripts: `composer.json`

## Agent Skills In This Repository

Use these local skills when implementing changes:

- `resources/docs/add-api`: Scaffold a complete consumer API from `add-api: <api-name>`, using `ExampleApi` as the reference.
- `resources/docs/add-php-ddd-feature`: Add backend features using DDD, Ports and Adapters, CQRS, Doctrine, and PHPUnit.
- `resources/docs/add-example-api-endpoint-controller`: Add consumer API endpoint controllers, Slim routes, authorization grouping, request parsing, responses, and OpenAPI docs, using `ExampleApi` as the reference implementation.
- `resources/docs/add-bruno-api-e2e-test`: Add YAML Bruno API E2E tests under `resources/bruno` for affected consumer APIs.
- `resources/docs/add-external-service`: Add a bounded external-service adapter and its configuration.
- `resources/docs/tolgee-i18n`: Synchronize translation keys with Tolgee.

## Commands

Prefer project scripts when dependencies are installed:

```sh
composer run start-apis
composer test
composer phpstan
composer cs-check
composer generate-example-api-spec
bin/doctrine migrations:diff
bin/doctrine migrations:migrate
bin/backendbase
```

Use targeted PHPUnit paths first when changing a bounded context:

```sh
vendor/bin/phpunit src/Backendbase/Domain/ExampleBoundedContext/Tests
```

## Agent Working Notes

- Use the real namespace and path `src/Backendbase/Infrastructure`.
- Treat `src/Backendbase/Domain/ExampleBoundedContext` as the bounded-context reference.
- Treat `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example` as the HTTP module reference.
- The current repository contains only `ExampleApi`. Scaffold another API before adding adapters for it.
- Update `resources/api-docs/example-api` and `resources/bruno/example-api` when a public ExampleApi route changes.
- Use targeted tests under `tests/Infrastructure/UseCase/ExampleApi` for HTTP adapter behavior.
- Report skipped checks with exact commands and blockers when local dependencies, extensions, services, or environment variables are unavailable.
