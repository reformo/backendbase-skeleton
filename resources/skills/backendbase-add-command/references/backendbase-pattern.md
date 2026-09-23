# Backendbase command pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Command contract | `Catalog/Contracts/Command` |
| Command handler | `Catalog/Application/CommandHandlers` |
| Business change | `Catalog/Domain` |
| Write capability | A context-owned repository or service port |
| Production implementation | `Catalog/Adapters` |
| Contract and handler tests | Context-owned `Tests` |

`Catalog` is illustrative. Use the discovered owning context.

## Minimal command

```php
final readonly class DiscontinueCatalogItem implements Command
{
    public function __construct(private CatalogItemId $itemId)
    {
    }

    public function itemId(): CatalogItemId
    {
        return $this->itemId;
    }

    public function toArray(): array
    {
        return ['itemId' => $this->itemId->toString()];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
```

## Minimal handler

```php
final readonly class DiscontinueCatalogItemHandler implements CommandHandler
{
    public function __construct(private CatalogItemWriteRepository $repository)
    {
    }

    /** @param DiscontinueCatalogItem $command */
    public function handle(Command $command): void
    {
        $item = $this->repository->get($command->itemId());
        $item->discontinue();
        $this->repository->save($item);
    }
}
```

Add transaction or event code only after discovering an explicit requirement and the target project's port.

## Registration and runtime resolution

The context registry identifies the handler. Add the mapping to `ServiceProvider::getHandlers()`:

```php
public static function getHandlers(): array
{
    return [DiscontinueCatalogItem::class => DiscontinueCatalogItemHandler::class];
}
```

The mapping does not register the handler service. Register the handler in the target composition root:

```php
$containerBuilder->addDefinitions([
    DiscontinueCatalogItemHandler::class => autowire(),
]);
```

An existing namespace glob is sufficient only when it matches the new handler path. Add a test that sends the real command through the container-backed command bus. A direct handler unit test cannot prove runtime resolution.

Use the target's production provider loader or a faithful test composition root. Assert that the handler and every constructor dependency resolve. Keep this composition test separate from handler orchestration and architecture tests.

## Transactional integration-event branch

Use this branch when the use case requires an integration event with local subscribers or queue delivery:

```php
$transaction->execute(function () use ($item, $integrationEvent): IntegrationEvent {
    $this->repository->save($item);

    return $integrationEvent;
});
```

The transaction must commit business writes, local subscriber writes, and any outbox row together. Return the complete event after database work. Keep database reads that control the write inside the same transaction. Pure in-memory validation or object construction can occur before it. In unmodified Backendbase, local subscribers run for both `DELIVER_VIA_QUEUE` values; only `true` adds an outbox row.

Do not call the broker in the callback. Test both commit and rollback. Force a local subscriber failure and verify rollback. When queue delivery is enabled, also force the outbox insert to fail and prove that no business state remains committed.

Do not create an integration event only to gain access to `IntegrationEventTransaction`. When atomic database work needs no integration event, use the target project's ordinary transaction port or report that the required transaction capability is absent.

## Synchronous domain-event branch

Use this branch only when same-process listener failure must participate in command rollback. Publish after the required persistence operation and before the transaction callback returns:

```php
$transaction->execute(function () use ($item, $domainEvent, $realIntegrationEvent): IntegrationEvent {
    $this->repository->save($item);
    $this->domainEventPublisher->publish($domainEvent);

    return $realIntegrationEvent;
});
```

The integration event in this example must already be required by the use case. It can use local or queue delivery. Without an integration event, use an ordinary transaction abstraction instead. Do not call `EventManager::dispatchEvent()` or a broker from the command handler.

Test the required order and force listener failure. Verify that persisted state and any outbox row roll back together.

## Current source behavior and limitations

- Current Backendbase contracts have no handler attributes. `RegistryHandlerResolver` reads the owning context's `ServiceProvider::getHandlers()` mapping.
- The selected mapping identifies a class but does not register it. The container must resolve the handler and its dependencies.
- The bus provides no validation, authorization, logging, retry, transaction middleware, or asynchronous dispatch.
- Current production handlers live in `Application/CommandHandlers`. Container config also contains older alternate glob patterns; do not select them when the target follows the current reference.
- Current Example handlers return their integration events through `IntegrationEventTransaction`. The wrapper dispatches local subscribers before commit and selects additional outbox publication from `DELIVER_VIA_QUEUE`. Do not create a fake event only to obtain a transaction.
- `AddEntryHandler` persists the aggregate and publishes its synchronous domain event inside the `IntegrationEventTransaction` callback. Its handler test asserts `transaction-start`, repository, domain event, then `transaction-end`.
- `ChangeEntryHandler` and `RemoveEntryHandler` resolve `EntryIdentity` through the write repository inside the transaction callback. Each callback returns the integration event after it knows the aggregate identifier.
- Patch-style nullable fields mean "not supplied" only when the public contract defines that meaning.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Contracts/CommandAndQueryContractsTest.php
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Application/CommandHandlers
vendor/bin/phpunit tests/Infrastructure/Adapters/CQRS
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

For movable context tests in an unmodified Backendbase project, use the context `Tests` root.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/2-cqrs.html`
- `resources/platform/04-cqrs.md`
- `resources/platform/24-feature-workflow.md`
- `src/Backendbase/Shared/CQRS/Command.php`
- `src/Backendbase/Shared/CQRS/CommandHandler.php`
- `src/Backendbase/Infrastructure/Adapters/CQRS/ContainerAwareCommandBus.php`
- `src/Backendbase/Domain/ExampleCatalog/ServiceProvider.php`
- `src/Backendbase/Domain/ExampleCatalog/Contracts/Command`
- `src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers`
- `src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers/AddEntryHandler.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers/ChangeEntryHandler.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers/RemoveEntryHandler.php`
- `src/Backendbase/Domain/ExampleCatalog/Domain/EntryIdentity.php`
- `src/Backendbase/Domain/ExampleCatalog/Contracts/DomainEvents/EntryAdded.php`
- `config/dependencies/modules.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Contracts/CommandAndQueryContractsTest.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Application/CommandHandlers/AddEntryHandlerTest.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Application/CommandHandlers/ChangeEntryHandlerTest.php`
- `tests/Functional/EntryLifecycleTest.php`
- `.github/workflows/quality-gates.yml`
