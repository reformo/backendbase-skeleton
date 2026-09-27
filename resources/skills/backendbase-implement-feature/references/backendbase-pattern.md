# Backendbase Cross-Layer Feature Pattern

Use relevant sections to reproduce the architecture in another PHP project. Do not reproduce product names or sample data. Source lists record provenance; they are not mandatory reading lists.

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
- `src/Backendbase/Domain/ExampleCatalog/`
- `src/Backendbase/Domain/ExampleCatalog/ServiceProvider.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php`
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
| Delivery mechanism | `src/Backendbase/Infrastructure/Inbound/` | Adapt HTTP and console code to the target framework. |
| Shared capability | `src/Backendbase/Shared/` | Add only framework-free concepts shared by multiple contexts. |
| Composition root | `config/dependencies/` and context `ServiceProvider.php` | Register ports, adapters, handlers, and subscribers explicitly. |
| Schema change | `resources/database/Migrations/` | Follow the target migration namespace and database rules. |
| Verification | Context `Tests`, plus root infrastructure and architecture tests | Mirror the target test ownership and runner. |

Discover unknown target mappings for affected roles before creating files. Reuse mappings already established from unchanged source. A matching role is more important than a matching directory name.

## Affected-delivery pass

Discover delivery surfaces from the target project instead of starting from a fixed product list. Check existing HTTP APIs, console commands, queue consumers, and public contract trees. Mark each surface as affected or not applicable.

Use the nearest complete API as the implementation reference, not as the only target. Current Backendbase contains one HTTP API, `ExampleApi`; that fact does not imply that another project has the same API or only one API. When a requested target is absent, confirm whether to scaffold it before adding adapters or documentation.

## Cross-layer implementation gates

For an endpoint-backed feature, verify these outcomes. Choose the implementation order from actual dependencies.

- Each affected API has an aligned public contract, domain behavior, command or query, handler, and required adapters.
- Focused tests prove the application path and its delivery boundary.
- Generate a schema diff only after mapping and repository behavior are established and the schema scope is authorized.
- Review generated SQL. Dry-run only against an identified, prepared target. Apply only with explicit authority for that database.
- Runtime behavior, OpenAPI, and maintained executable examples agree.

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
- `tests/Architecture/SharedFrameworkBoundaryTest.php`
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

Backendbase command and query messages use the context registry. Keep the contract free of handler metadata and map it in `ServiceProvider::getHandlers()`:

```php
final readonly class RegisterOrder implements Command
{
    public function __construct(public string $orderId)
    {
    }
}

public static function getHandlers(): array
{
    return [RegisterOrder::class => RegisterOrderHandler::class];
}
```

The registry identifies the handler. The container must resolve that handler and its dependencies. Current handlers live in `Application/CommandHandlers` and `Application/QueryHandlers`.

A handler coordinates work. Put state rules on the model. Put database and provider code behind ports.

## Transactional Publication

For a database change and an external integration event, use one local transaction:

```php
$this->transaction->execute(function () use ($aggregate, $domainEvent, $integrationEvent): IntegrationEvent {
    $this->repository->add($aggregate);
    $this->domainEventPublisher->publish($domainEvent);

    return $integrationEvent;
});
```

The transaction must commit business state, local integration subscriber writes, and optional outbox publication together. In unmodified Backendbase, the wrapper dispatches the returned event before commit. Both `DELIVER_VIA_QUEUE` values run local subscribers; true also appends one outbox row. The callback and local subscribers must not call a broker, network service, process, or filesystem. Discover the corresponding transaction and dispatch owners in other target projects.

Consumers remain at-least-once. Use inbox idempotency for database work and an external-effect inbox for provider calls. Keep external event contracts versioned.

## Client-visible Write Results

Inspect the target's response contract and identifier ownership. In an unmodified Backendbase project, command handlers and buses return `void`. Select one of these approaches:

- Generate the public identifier before a create command or reuse an existing resource's public identity. Carry it in the command and return it only after successful execution.
- For a resource representation, call one application orchestrator in the owning context. It executes the command, waits for commit, then reads through a query or read port. It returns a declared read model or immutable result object. Keep input and result contracts in the contract layer and HTTP mapping in the delivery adapter.

Preserve context isolation and authorization for both the command and read, including direct read-port calls. Keep authoritative write lookup and transaction control in the handler or its called application service. Do not mutate the command or use events as a response channel.

Verify that the read source meets the required consistency. An asynchronous projection can lag, and a later read does not guarantee an exact commit snapshot. Define missing-result and read-failure behavior after commit. A read failure does not undo the write. Do not repeat the command to recover a response. Neither approach retrieves an unpersisted handler-only result.

Test identifier equality, command-failure behavior, and real container resolution. For orchestration, also test commit-before-read order, visibility, read authorization, and defined read-failure behavior.

Current Backendbase create endpoints demonstrate the identifier approach. The orchestrator approach is permitted guidance, not an existing create-endpoint implementation.

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
- Shared-header enforcement, CORS, and the OpenAPI `datetime` timestamp format still need alignment. Pagination and security failure status codes match their documented behavior.
- The sample aggregate does not automatically drain recorded events. Its command handler publishes a domain event explicitly.
- New Backendbase migrations should extend `BackendbaseAbstractMigration`. Older migrations use a different base.
- Direct Doctrine entity paths have broader tool support than nested entity paths in the current repository.
- Notification providers are wired, but no notification queue contract or consumer is registered. Define complete message and provider contracts for queued delivery.
- The object-storage example has retry, content-type, and response-type limits. Add bounded retry and preserve object metadata.
- `en-US.php` currently loads the Turkish dictionary. Use language-correct catalogs in new projects.
- Shared object mapping drops unknown keys and permits scalar coercion. Use explicit boundary validation when the contract is strict.

## Verification Baseline

Use target-project commands. The Backendbase equivalents are:

```text
vendor/bin/phpunit <smallest relevant path>
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
composer validate-example-api-spec
composer test
```

These commands are options selected by changed behavior and target policy, not a mandatory sequence. Reuse successful checks across skills while relevant inputs remain unchanged.

`phpstan.neon` sets level 8. Run API generation and validation only for affected APIs. Run deployment script tests when release tooling changes. Use document checks for prose-only work.
