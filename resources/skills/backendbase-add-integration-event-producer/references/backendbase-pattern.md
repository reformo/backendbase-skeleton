# Backendbase integration-event producer pattern

Use this pattern only when an existing transactional messaging runtime must publish a new business fact.

## Target discovery

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, bounded contexts, command handlers, integration-event contracts, clocks, outbox transaction port, service identity configuration, and tests.
3. Find the nearest working producer in the same context.
4. Confirm the fact crosses a process or service boundary. Use a domain event for internal domain coordination.
5. Confirm no released event already represents the same fact.

Do not copy `Backendbase\`, `ExampleBoundedContext`, `Example_*`, the default service name, identifiers, or payload fields.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Business fact | Integration-event class | Name it in past tense from the domain event. |
| Wire schema | versioned payload class | Use explicit typed fields and JSON names. |
| Occurrence time | event clock | Reuse the target clock abstraction or convention. |
| Atomic write | `IntegrationEventTransaction` | Use the existing outbox transaction port. |
| Producer | command handler | Return the event after transactional business work. |
| Contract proof | schema test | Assert exact name, version, and payload. |

## Naming and versioning

Backendbase new event types use:

```text
{PascalCaseServiceName}_{PascalCaseEventClassName}
```

The service name comes from runtime configuration. A target project can use a different convention. Discover it before selecting a name.

Once published, the name is a compatibility contract. Begin a new event at the target's current initial version. Backendbase uses strings such as `1.0`.

## Small producer example

The `Billing_InvoiceIssued` identity is illustrative.

```php
final readonly class InvoiceIssuedPayload
{
    public function __construct(private string $invoiceId, private int $totalMinor)
    {
    }

    /** @return array{invoiceId: string, totalMinor: int} */
    public function toArray(): array
    {
        return ['invoiceId' => $this->invoiceId, 'totalMinor' => $this->totalMinor];
    }
}

final class InvoiceIssued implements IntegrationEvent
{
    public const string EVENT_TYPE = 'Billing_InvoiceIssued';
    public const string EVENT_VERSION = '1.0';
    public const bool IS_MESSAGING_EVENT = true;

    use IntegrationEventTrait;

    public function __construct(private readonly InvoiceIssuedPayload $payload)
    {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function eventName(): string
    {
        return self::EVENT_TYPE;
    }

    public function eventVersion(): string
    {
        return self::EVENT_VERSION;
    }

    /** @return array<string, mixed> */
    public function getEventArguments(): array
    {
        return $this->payload->toArray();
    }
}
```

Adapt trait initialization to the target. Backendbase uses its project clock helper.

## Command-handler boundary

Return the payload from the transaction callback after authoritative reads and writes:

```php
$this->integrationEventTransaction->execute(
    function () use ($invoice, $command): IntegrationEvent {
        $this->invoiceRepository->save($invoice);

        return new InvoiceIssued(new InvoiceIssuedPayload(
            invoiceId: $command->invoiceId(),
            totalMinor: $command->totalMinor(),
        ));
    },
);
```

Do not pass the command or aggregate into the payload. The callback can mutate database state and run synchronous work that must control rollback. It cannot publish to a broker or call a remote service.

## Workflow

1. Define the stable business fact and owning service.
2. Select an event name and version.
3. Define a minimal typed payload from consumer needs, without exposing the aggregate.
4. Create event and payload contracts in the owning context.
5. Inject the existing transaction port into the handler.
6. Move authoritative database reads and required mutations into its callback, then return the event.
7. Add exact schema and serialization tests.
8. Add a handler-order test that proves repository and synchronous domain work occur inside the callback.
9. Run atomic commit and rollback tests for the outbox adapter.
10. Document the published contract and required consumer rollout.

## Invariants and risks

- `EVENT_TYPE`, version, and serialized payload form one compatibility contract.
- `IS_MESSAGING_EVENT` is a convention. Backendbase outbox storage does not check it before insertion.
- Use explicit JSON-compatible scalars and arrays.
- Do not serialize commands, aggregates, Doctrine entities, or vendor objects.
- Direct broker publication from a command handler loses atomicity.
- An event without a matching consumer can still enter the Backendbase outbox and later be rejected by the standard consumer.

## Authorization boundary

Creating producer code and migrations is separate from applying schema changes, running relays, publishing test messages, or changing a live queue. Request authority for those actions.

## Verification

```sh
vendor/bin/phpunit tests/Domain/ExampleBoundedContext/Contracts/IntegrationEvents/IntegrationEventSchemaTest.php
vendor/bin/phpunit tests/Domain/ExampleBoundedContext/Application/CommandHandlers/AddNewExampleHandlerTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationEventTransactionTest.php
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

Run the target project's new schema and handler tests first. The listed Backendbase paths show the verified test roles. Also map the actual producer payload into the consumer carrier when a consumer is added in the same change.

## Completion report

Report event identity, version, exact payload fields, occurrence-time source, handler and transaction boundary, tests, documentation, consumer dependencies, and external actions not performed.

## Provenance

Verified on 2026-08-28 from:

- `src/Backendbase/Shared/Domain/Messaging/IntegrationEvent.php`
- `src/Backendbase/Shared/Domain/Messaging/IntegrationEventTrait.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/IntegrationEvents/NewExampleAdded.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/IntegrationEvents/V1/NewExampleAddedPayload.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Application/CommandHandlers/AddNewExampleHandler.php`
- `tests/Domain/ExampleBoundedContext/Contracts/IntegrationEvents/IntegrationEventSchemaTest.php`
- `tests/Domain/ExampleBoundedContext/Application/CommandHandlers/AddNewExampleHandlerTest.php`
- `resources/platform/05-integration-event-contracts.md`
- `resources/platform/11-messaging-outbox.md`
- `resources/docs/3-integration-events.html`
