# Backendbase domain-event pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Event contract | `Catalog/Contracts/DomainEvents` |
| Listener | `Catalog/Application/DomainEventListener` |
| Publish orchestration | The owning command handler or application service |
| Publisher implementation | Infrastructure adapter for the shared publisher port |
| Focused evidence | Context-owned event, listener, handler, composition, and lifecycle tests |

`Catalog` is illustrative. Use the discovered owning context.

## Minimal event

```php
#[DomainEventListenerAttribute(CatalogItemDiscontinuedListener::class)]
final class CatalogItemDiscontinued implements DomainEvent
{
    use DomainEventTrait;

    public function __construct(private string $itemId)
    {
        $this->initializeOccurredOn();
    }

    public function eventName(): string
    {
        return self::class;
    }

    public function getEventArguments(): array
    {
        return ['itemId' => $this->itemId];
    }
}
```

## Minimal listener guard

```php
public function handle(DomainEvent $event): void
{
    if (! $event instanceof CatalogItemDiscontinued) {
        throw new UnexpectedValueException($event::class . ' is not supported.');
    }

    $this->projection->markDiscontinued($event);
}
```

Use project-owned ports for listener effects. Do not add the illustrative projection unless the requested use case needs it.

## Transaction routing

Synchronous publication participates in rollback only when it runs inside the transaction that owns the write. Publish after the required persistence operation and before the callback returns:

```php
$transaction->execute(function () use ($item, $domainEvent, $realIntegrationEvent): IntegrationEvent {
    $this->repository->save($item);
    $this->domainEventPublisher->publish($domainEvent);

    return $realIntegrationEvent;
});
```

Use this transaction shape only when the use case already requires an integration event. The event can use local delivery or queue delivery. A domain event alone does not justify creating an integration event. When no integration event exists, use the target project's ordinary transaction abstraction or stop and report the missing capability.

Do not put network, broker, process, or filesystem work in a transactional listener. Do not call the internal integration-event manager from the command handler.

## Publisher composition and reachability

The listener attribute supplies a class name. It does not prove that the container can build the listener. Add a composition test that:

1. builds the real test container or faithful production provider set;
2. resolves the publisher and attributed listener;
3. publishes the concrete event through `DomainEventPublisher`;
4. verifies one listener invocation and the required failure propagation.

A direct listener test proves listener behavior only. An architecture test proves dependency direction only.

## Current source behavior and limitations

- `ContainerAwareDomainEventPublisher` reads the first listener attribute and resolves that class from the container.
- The current production Example flow publishes from `AddEntryHandler` inside `IntegrationEventTransaction`.
- Publication is synchronous. Listener exceptions propagate to the caller.
- `AddEntryHandlerTest` asserts that repository persistence and domain-event publication both occur before the transaction callback ends.
- The listener does not require `ServiceProvider` subscriber metadata.
- Shared `Aggregate::recordEvent()` exists, but current production code has no automatic recorded-event drain.
- The production Example domain event carries a command object. That is an example choice, not a required payload style. Prefer explicit fields when they reduce coupling.
- A synchronous domain listener must not perform business-relevant network, process, filesystem, or direct broker work inside a transaction.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Application/CommandHandlers
vendor/bin/phpunit tests/Shared/Domain
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

For new movable context tests in an unmodified Backendbase project, use the context `Tests` root. Existing root Example tests remain source evidence and need not move.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/1-bounded-contexts.html`
- `resources/platform/03-bounded-contexts.md`
- `resources/platform/24-feature-workflow.md`
- `src/Backendbase/Domain/ExampleCatalog/Contracts/DomainEvents/EntryAdded.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/DomainEventListener/EntryAddedListener.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers/AddEntryHandler.php`
- `src/Backendbase/Shared/Domain/DomainEvent.php`
- `src/Backendbase/Shared/Domain/DomainEventTrait.php`
- `src/Backendbase/Infrastructure/Adapters/DomainEvents/ContainerAwareDomainEventPublisher.php`
- `src/Backendbase/Shared/Domain/Attributes/DomainEventListener.php`
- `src/Backendbase/Shared/Domain/Aggregate.php`
- `config/dependencies/modules.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Application/CommandHandlers/AddEntryHandlerTest.php`
- `tests/Shared/Domain/SharedDomainSupportTest.php`
- `tests/Shared/DomainEventsTest.php`
- `.github/workflows/quality-gates.yml`
