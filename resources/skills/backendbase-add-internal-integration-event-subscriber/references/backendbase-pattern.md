# Backendbase internal integration-event subscriber pattern

An internal integration-event subscriber handles a producer `IntegrationEvent` synchronously in the current process. It is not a domain-event listener and it is not part of the queue consumer path.

## Target discovery

Resolve only unknown facts needed by the affected behavior. Reuse verified facts while their sources remain unchanged.

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, integration-event interfaces, event manager, container registration, bounded-context provider, and tests.
3. Search for every explicit internal integration-event dispatch call.
4. Identify the boundary that owns dispatch and the required transaction behavior.
5. Find the nearest subscriber with the same dependency type.

Do not copy `Backendbase\`, `ExampleCatalog`, `Example_*`, subscriber names, or logger messages.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Input | producer `IntegrationEvent` | Subscribe to one existing event contract. |
| Subscriber contract | `IntegrationEventSubscriber` | Use the internal interface only. |
| Dispatch | `IntegrationEventTransaction` calls `EventManager::dispatchEvent()` | Identify the transaction owner that invokes local subscribers before commit. |
| Registration | bounded-context `ServiceProvider` | Register event names and subscriber class. |
| Resolution | `ContainerAwareEventManager` | Follow target container construction rules. |
| Test | event-manager and provider tests | Prove dispatch and type safety. |

## Small subscriber example

The event and dependency names are illustrative.

```php
final readonly class RefreshInvoiceProjectionSubscriber implements IntegrationEventSubscriber
{
    public function __construct(private InvoiceProjectionRefresher $refresher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [InvoiceIssued::EVENT_TYPE];
    }

    public function handle(IntegrationEvent $integrationEvent): void
    {
        if (! $integrationEvent instanceof InvoiceIssued) {
            throw new UnexpectedValueException('An unsupported integration event was received.');
        }

        $this->refresher->refresh($integrationEvent->invoiceId());
    }
}
```

Backendbase internal registration contains no message carrier or version:

```php
[
    'events' => RefreshInvoiceProjectionSubscriber::getSubscribedEvents(),
    'subscriberFQCN' => RefreshInvoiceProjectionSubscriber::class,
]
```

## Dispatch ownership

Registration alone does not execute the subscriber. In an unmodified Backendbase project, `IntegrationEventTransaction::execute()` runs business work and passes the returned event to the event manager before commit:

```php
$eventManager->dispatchEvent($event);
```

Do not add a second dispatch call to a command handler. Return the event from the transaction callback so the wrapper owns dispatch. In other target projects, verify the corresponding transaction owner before adding the subscriber.

Backendbase runs local subscribers for both `DELIVER_VIA_QUEUE` values. A true flag also writes one outbox row after local subscribers complete. Producer dispatch rejects a missing transaction before any subscriber runs. Local subscriber repositories and the outbox writer must use the same connection. A subscriber failure rolls back database work. Keep network, process, and filesystem effects out of local subscribers.

## Exact and wildcard subscriptions

The Backendbase event manager supports exact strings and `fnmatch` wildcard patterns. It hashes subscriber class names, so one class matched by an exact and wildcard entry runs once.

Use wildcards only when the business request requires a stable family of events. A broad internal wildcard can also match external event names and cause interface-type failures.

## Workflow

1. Confirm synchronous in-process behavior is required.
2. Identify the explicit dispatch owner and transaction boundary.
3. Add one subscriber with a narrow application port.
4. Check the runtime event type before using specialized methods.
5. Register only event names and subscriber class.
6. Add tests for matching dispatch, unsupported input, dependencies, and registration.
7. Run architecture checks to preserve context and adapter boundaries.

## Invariants and risks

- Internal subscribers receive `IntegrationEvent`, not `EventMessage`.
- Internal and external subscriber interfaces are not interchangeable.
- Registration without dispatch creates dead code.
- Subscriber failure is synchronous and can fail its caller.
- Command handlers must not call internal integration dispatch directly.
- One bounded context must not import another context's implementation.
- Avoid logging complete event payloads when they can contain sensitive data.
- In unmodified Backendbase, subscriber resolution requires an explicit constructor, including an empty constructor when no dependencies exist. The registry creates a fresh subscriber through reflection and resolves each constructor dependency from the container by type, then by parameter name.

## Authorization boundary

Code changes do not authorize executing subscriber effects against live data. Use doubles or isolated test storage unless the user approves an integration run.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit src/Backendbase/Domain/ExampleCatalog/Tests/ServiceProviderTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/EventManager
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

Run the target project's new subscriber test first. The listed Backendbase paths show verified registration, dispatch, and architecture test roles.

## Completion report

Report the event, subscriber, dispatch owner, transaction behavior, registration, wildcard use, tests, and whether a verified invocation path exists.

## Provenance

Verified against current source on 2026-09-23:

- `src/Backendbase/Shared/Domain/Messaging/IntegrationEventSubscriber.php`
- `src/Backendbase/Shared/Services/EventManager/EventManager.php`
- `src/Backendbase/Infrastructure/Adapters/EventManager/ContainerAwareEventManager.php`
- `config/dependencies/modules.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/IntegrationEventSubscribers/EntryAddedSubscriber.php`
- `src/Backendbase/Domain/ExampleCatalog/ServiceProvider.php`
- `tests/Infrastructure/Adapters/EventManager/ContainerAwareEventManagerTest.php`
- `resources/platform/06-integration-event-consumers.md`
- `resources/docs/3-integration-events.html`
