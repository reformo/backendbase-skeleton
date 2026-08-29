# Backendbase Cross-Layer Feature Pattern

Use this reference to reproduce the architecture in another PHP project. Do not reproduce product names or sample data.

## Source Provenance

This pattern comes from the current Backendbase source and these primary guides:

- `resources/docs/project.md`
- `resources/platform/01-repository-map.md`
- `resources/platform/README.md`
- `resources/platform/02-architecture.md`
- `resources/platform/03-bounded-contexts.md`
- `resources/platform/04-cqrs.md`
- `resources/platform/07-http-api.md`
- `resources/platform/09-persistence.md`
- `resources/platform/11-messaging-outbox.md`
- `resources/platform/12-messaging-consumers.md`
- `resources/platform/17-testing.md`
- `resources/platform/24-feature-workflow.md`
- `src/Backendbase/Domain/ExampleBoundedContext/`
- `src/Backendbase/Domain/ExampleBoundedContext/ServiceProvider.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php`
- `config/dependencies/`
- `resources/api-docs/example-api/`
- `resources/bruno/example-api/`
- `resources/database/Migrations/`
- `deployment/release.json`
- `tests/Architecture/`

The numbered HTML files in `resources/docs/` give background. Prefer current code, tests, and `resources/platform/` when an older explanation conflicts with them.

## Adaptation Map

| Backendbase role | Current source | Target-project decision |
| --- | --- | --- |
| Bounded context | `src/Backendbase/Domain/{Context}/` | Use the target source root and business namespace. |
| Domain model | `{Context}/Domain/` | Keep invariants and state transitions here. |
| Public context contract | `{Context}/Contracts/` | Put commands, queries, ports, and event schemas here. |
| Application orchestration | `{Context}/Application/` | Keep handlers and subscribers free of delivery frameworks. |
| Context adapter | `{Context}/Adapters/` | Implement context ports without reversing dependencies. |
| Delivery mechanism | `src/Backendbase/Infrastructure/UseCase/` | Adapt HTTP and console code to the target framework. |
| Shared capability | `src/Backendbase/Shared/` | Add only concepts shared by multiple contexts. |
| Composition root | `config/dependencies/` and context `ServiceProvider.php` | Register ports, adapters, handlers, and subscribers explicitly. |
| Schema change | `resources/database/Migrations/` | Follow the target migration namespace and database rules. |
| Verification | Context `Tests`, plus root infrastructure and architecture tests | Mirror the target test ownership and runner. |

Discover the target mappings before creating files. A matching role is more important than a matching directory name.

## Affected-delivery pass

Discover delivery surfaces from the target project instead of starting from a fixed product list. Check existing HTTP APIs, console commands, queue consumers, and public contract trees. Mark each surface as affected or not applicable.

Use the nearest complete API as the implementation reference, not as the only target. Current Backendbase contains one HTTP API, `ExampleApi`; that fact does not imply that another project has the same API or only one API. When a requested target is absent, confirm whether to scaffold it before adding adapters or documentation.

## Cross-layer implementation gates

For an endpoint-backed feature, use these gates:

1. Draft the public contract for each affected API.
2. Define domain behavior and the command or query contract.
3. Define ports, then implement adapters and the handler.
4. Prove the application path with focused tests before delivery adapters.
5. Generate a schema diff only after mapping and repository behavior are final and the schema scope is approved.
6. Review the diff and dry-run it against an identified target.
7. Apply it only with explicit authority for that database.
8. Add the controller and route after the application path is proven.
9. Reconcile runtime behavior with OpenAPI and executable examples.

An unauthorized migration application is an external handoff, not a successful verification. Do not treat application code approval as database authority.

## Dependency Direction

Keep these constraints:

```text
Domain model <- contracts <- application orchestration
     ^                           |
     |                           v
ports <---------------------- adapters

delivery and configuration -> application contracts
shared -> no application module
one bounded context -> no direct dependency on another context
```

Backendbase enforces these rules in:

- `tests/Architecture/DomainPurityTest.php`
- `tests/Architecture/AdapterDirectionTest.php`
- `tests/Architecture/BoundedContextIsolationTest.php`
- `tests/Architecture/FrameworkImportBoundaryTest.php`
- `tests/Architecture/SharedDependencyBoundaryTest.php`
- `tests/Architecture/ApplicationDependencyBoundaryTest.php`
- `tests/Architecture/SharedCoreFrameworkBoundaryTest.php`
- `tests/Architecture/InboundAdapterDependencyBoundaryTest.php`
- `tests/Architecture/OutboundAdapterDependencyBoundaryTest.php`
- `tests/Architecture/AttributeTargetBoundaryTest.php`

Do not import Doctrine, Slim, Symfony, AWS, PSR HTTP, or other framework types into business layers. Define a port and place the framework adapter outside the core.

## Minimum Feature Slice

A cross-layer write feature normally contains only the required items:

1. Domain behavior and value objects.
2. A command contract and one handler.
3. A write port and its adapter.
4. A migration when persistence changes.
5. An HTTP action or console command when a delivery boundary is required.
6. An integration event and outbox transaction when another service needs the fact.
7. Unit, adapter, boundary, contract, and architecture tests that match the changed surfaces.

A read feature normally uses a query, query handler, read port, read model, and delivery mapping. Do not force writes and reads through one repository.

## CQRS Contract

Current Backendbase command and query messages use one positional handler attribute:

```php
#[CQRSHandler(RegisterOrderHandler::class)]
final readonly class RegisterOrder implements Command
{
    public function __construct(public string $orderId)
    {
    }
}
```

The command bus reads attribute argument index `0`. Preserve exactly one positional argument when reproducing this mechanism. Current handlers live in `Application/CommandHandlers` and `Application/QueryHandlers`.

A handler coordinates work. Put state rules on the model. Put database and provider code behind ports.

## Transactional Publication

For a database change and an external integration event, use one local transaction:

```php
$this->transaction->execute($integrationEvent, function () use ($aggregate, $domainEvent): void {
    $this->repository->add($aggregate);
    $this->domainEventPublisher->publish($domainEvent);
});
```

The transaction must write the business state and outbox row together. It must not call a broker, network service, process, or filesystem inside the callback.

Consumers remain at-least-once. Use inbox idempotency for database work and an external-effect inbox for provider calls. Keep external event contracts versioned.

## Boundary Rules

- Validate HTTP path, query, body, header, and authentication data before dispatch.
- Use `kebab-case` for URL path parameter names when the target standard requires it.
- Use `camelCase` for query, request-body, and response fields.
- Return stable problem-details fields without secrets or stack traces.
- Keep OpenAPI, runtime behavior, and executable API examples in sync.
- Validate console arguments and configuration before invoking application logic.
- Set explicit timeouts for network clients.

## Registration Checklist

Check every applicable registration point:

- Composer PSR-4 namespace.
- Context service provider.
- Port-to-adapter definition.
- Command or query handler discovery.
- HTTP module and route table.
- Middleware order.
- Console command list.
- Event subscriber registry and event version.
- Queue driver and message processor.
- Readiness-check collection.
- Migration namespace and entity discovery.
- Test bootstrap and fixtures.

Backendbase discovers context service providers one directory below `src/Backendbase/Domain`. Prefer a direct context directory unless the target discovery code explicitly supports another layout.

## Known Source Limits to Correct

Do not copy these current source conditions into new work:

- The sample version 1 producer and registered carrier use a nested `command` shape. Preserve exact producer-to-carrier mapping through the real dispatcher.
- Some sample API runtime fields, pagination behavior, status codes, and OpenAPI declarations differ. Make the runtime, specification, and tests agree.
- The sample aggregate does not automatically drain recorded events. Its command handler publishes a domain event explicitly.
- New Backendbase migrations should extend `BackendbaseAbstractMigration`. Older migrations use a different base.
- Direct Doctrine entity paths have broader tool support than nested entity paths in the current repository.
- The notification example has incomplete provider wiring. Treat its interfaces and failure model as evidence, not the whole implementation.
- The object-storage example has retry, content-type, and response-type limits. Add bounded retry and preserve object metadata.
- `en-US.php` currently loads the Turkish dictionary. Use language-correct catalogs in new projects.
- Shared object mapping drops unknown keys and permits scalar coercion. Use explicit boundary validation when the contract is strict.

## Verification Baseline

Use target-project commands. The Backendbase equivalents are:

```text
vendor/bin/phpunit <smallest relevant path>
vendor/bin/phpunit tests/Architecture
composer phpstan
composer cs-check
composer validate-example-api-spec
composer test
```

`phpstan.neon` sets level 8. Run API generation and validation only for affected APIs. Run deployment script tests when release tooling changes.
