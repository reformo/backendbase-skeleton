# Backendbase command pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Command contract | `Catalog/Contracts/Command` |
| Command handler | `Catalog/Application/CommandHandlers` |
| Business change | `Catalog/Domain` |
| Write capability | A context-owned repository or service port |
| Production implementation | `Catalog/Adapters` |
| Contract and handler tests | Context-owned `Tests`; existing root context tests remain source evidence |

`Catalog` is illustrative. Use the discovered owning context.

## Minimal command

```php
#[CQRSHandler(DiscontinueCatalogItemHandler::class)]
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

The handler attribute supplies a class name. It does not register that class. Match the target composition root:

```php
$containerBuilder->addDefinitions([
    DiscontinueCatalogItemHandler::class => autowire(),
]);
```

An existing namespace glob is sufficient only when it matches the new handler path. Add a test that sends the real command through the container-backed command bus. A direct handler unit test cannot prove runtime resolution.

Use the target's production provider loader or a faithful test composition root. Assert that the handler and every constructor dependency resolve. Keep this composition test separate from handler orchestration and architecture tests.

## Transactional integration-event branch

Use this branch only when another service must receive a business fact:

```php
$transaction->execute($integrationEvent, function () use ($item): void {
    $this->repository->save($item);
});
```

The transaction must persist the business state and outbox row together. Keep database reads that control the write inside the same transaction when consistency needs locking. Pure in-memory validation or object construction can occur before it.

Do not call the broker in the callback. Test both commit and rollback. Force the outbox insert to fail and prove that no business state remains committed.

Do not create an integration event only to gain access to `IntegrationEventTransaction`. An integration event represents a real cross-process compatibility contract. When atomic database work needs no outbound fact, use the target project's ordinary transaction port or report that the required transaction capability is absent.

## Synchronous domain-event branch

Use this branch only when same-process listener failure must participate in command rollback. Publish after the required persistence operation and before the transaction callback returns:

```php
$transaction->execute($realIntegrationEvent, function () use ($item, $domainEvent): void {
    $this->repository->save($item);
    $this->domainEventPublisher->publish($domainEvent);
});
```

The integration event in this example must already be required by the use case. Without a real outbound event, use an ordinary transaction abstraction instead. Do not call `EventManager::dispatchEvent()` or a broker from the command handler.

Test the required order and force listener failure. Verify that persisted state and any outbox row roll back together.

## Current source behavior and limitations

- The command bus reflects the command class, reads the first `CQRSHandler` attribute, and reads positional argument index `0`.
- The attribute identifies a class but does not register it. The container must resolve the handler and its dependencies.
- The bus provides no validation, authorization, logging, retry, transaction middleware, or asynchronous dispatch.
- Current production handlers live in `Application/CommandHandlers`. Container config also contains older alternate glob patterns; do not select them when the target follows the current reference.
- Current Example handlers use `IntegrationEventTransaction` because their use cases publish integration events. Do not create a fake event only to obtain a transaction.
- `AddNewExampleHandler` persists the aggregate and publishes its synchronous domain event inside the `IntegrationEventTransaction` callback. Its handler test asserts `transaction-start`, repository, domain event, then `transaction-end`.
- Patch-style nullable fields mean "not supplied" only when the public contract defines that meaning.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Contracts/CommandAndQueryContractsTest.php
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Application/CommandHandlers
vendor/bin/phpunit tests/Shared/CQRS
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
vendor/bin/phpunit tests/Architecture
composer phpstan
composer cs-check
```

For new movable context tests in an unmodified Backendbase project, use the context `Tests` root. Existing root Example tests remain verified behavior evidence; do not relocate them during this change.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/2-cqrs.html`
- `resources/platform/04-cqrs.md`
- `resources/platform/24-feature-workflow.md`
- `src/Backendbase/Shared/CQRS/Command.php`
- `src/Backendbase/Shared/CQRS/CommandHandler.php`
- `src/Backendbase/Shared/CQRS/ContainerAwareCommandBus.php`
- `src/Backendbase/Shared/CQRS/Attributes/CQRSHandler.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/Command`
- `src/Backendbase/Domain/ExampleBoundedContext/Application/CommandHandlers`
- `src/Backendbase/Domain/ExampleBoundedContext/Application/CommandHandlers/AddNewExampleHandler.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/DomainEvents/ExampleAdded.php`
- `config/dependencies/modules.php`
- `tests/Domain/ExampleBoundedContext/Contracts/CommandAndQueryContractsTest.php`
- `tests/Domain/ExampleBoundedContext/Application/CommandHandlers/AddNewExampleHandlerTest.php`
- `tests/Functional/ExampleLifecycleTest.php`
- `.github/workflows/quality-gates.yml`
