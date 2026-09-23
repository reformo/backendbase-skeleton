# Backendbase external integration-event subscriber pattern

An external subscriber consumes a queue-delivered, versioned producer payload through a typed carrier and the inbox transaction.

## Target discovery

Resolve only unknown facts needed by the affected behavior. Reuse verified facts while their sources remain unchanged.

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, the consuming context, external carrier conventions, event manager, registry, mapper, inbox, failure policy, container, and tests.
3. Obtain the producer's exact serialized payload, event name, and version from source or a published contract.
4. Determine whether subscriber work is database-only or calls an external provider.
5. Find the nearest carrier and subscriber from the same source service.

Do not copy `Backendbase\`, `ExampleCatalog`, `Example_NewExampleAdded_Event`, version-one fixture data, queue names, or source-service folders.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Producer identity | queue `messageBody` plus version | Use the exact published contract. |
| Consumer identity | producer name plus `_Event` | Follow the target processor's derivation rule. |
| Carrier | `Contracts/ExternalIntegrationEvents/V{n}` | Match the serialized payload exactly. |
| Subscriber | `ExternalIntegrationEventSubscriber` | Keep behavior narrow and application-owned. |
| Registry | name and version to carrier | Register one unique key per schema. |
| Database idempotency | `InboxMessageTransaction` | Key by consumer and message ID. |
| External-effect safety | `ExternalEffectInbox` | Claim before the call and stop on unknown outcome. |

## Small carrier and subscriber example

The source service and event names are illustrative.

```php
final readonly class InvoiceIssuedMessage implements EventMessage
{
    public function __construct(private string $invoiceId, private int $totalMinor)
    {
    }

    public function invoiceId(): string
    {
        return $this->invoiceId;
    }

    /** @return array{invoiceId: string, totalMinor: int} */
    public function toArray(): array
    {
        return ['invoiceId' => $this->invoiceId, 'totalMinor' => $this->totalMinor];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

final readonly class InvoiceIssuedExternalSubscriber implements ExternalIntegrationEventSubscriber
{
    public const string EVENT_TYPE = 'Billing_InvoiceIssued_Event';

    public function __construct(private InvoiceImporter $importer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [self::EVENT_TYPE];
    }

    public function handle(EventMessage $message): void
    {
        if (! $message instanceof InvoiceIssuedMessage) {
            throw new UnexpectedValueException('An unsupported event message was received.');
        }

        $this->importer->import($message->invoiceId());
    }
}
```

## Registration shape

Backendbase discovers this data from each bounded-context service provider:

```php
[
    'events' => InvoiceIssuedExternalSubscriber::getSubscribedEvents(),
    'subscriberFQCN' => InvoiceIssuedExternalSubscriber::class,
    'messageFQCN' => InvoiceIssuedMessage::class,
    'eventVersion' => InvoiceIssued::EVENT_VERSION,
]
```

When producer source code is not available, use the literal published version from its contract. Do not import another bounded context only to read a constant.

## Runtime flow

1. The transport normalizes the message.
2. The processor validates event name, version, message ID, and consumer name.
3. The processor derives the consumer event name.
4. The inbox inserts `(consumer_name, message_id)`.
5. The registry resolves the carrier by name and version.
6. The mapper creates the typed carrier.
7. The event manager dispatches external subscribers.
8. Database work and `processed_at` commit together.
9. A processed duplicate acknowledges without dispatch.

## Database work versus external effects

Use the ordinary inbox transaction only for database work that can share its transaction. It commits the database mutation and `processed_at` together.

For email, payment, storage, or another provider call, use a durable external-effect protocol:

1. Validate the complete message before acquiring the effect claim.
2. Derive one stable effect identity from the consumer, message ID, and intended effect.
3. Persist a unique claim before the provider call.
4. Pass the same stable idempotency key to the provider when it supports one.
5. Call the provider outside the database transaction.
6. Record completion only after a confirmed provider result.
7. Mark or preserve `outcome_unknown` when the call may have succeeded or completion recording fails.
8. Block automatic provider retry for `outcome_unknown`.
9. Reconcile with the provider's status API, idempotency lookup, or an authorized manual process.

Backendbase keys the external-effect row by `(consumer_name, message_id)` and stores the effect name as metadata. This permits one effect per key. Redesign the unique key or use a distinct stable consumer identity when one message must start multiple independent effects.

The current adapter uses a lease token and `claimed_until`. An active claim returns in-progress. An expired incomplete claim becomes outcome-unknown and does not run again. An exception after the callback starts and a failure to record completion both become outcome-unknown.

This protocol prevents an automatic duplicate start. It does not prove that the provider completed the effect. Add operator-visible reconciliation. The repository has no automatic reconciliation worker.

## Failure classification

Classify by the point of failure:

| Class | Examples | Outcome |
| --- | --- | --- |
| Permanent contract or domain failure | Missing metadata, unknown version, wrong subscriber type, invalid payload, deterministic business rejection | Reject or use bounded permanent-failure policy. Do not dispatch again. |
| Retryable before external effect | Temporary database or registry failure, active external-effect lease | Retry with the bounded consumer policy. The provider call has not started. |
| Unknown after external effect starts | Provider timeout after request transmission, callback exception, completion-record failure, expired incomplete claim | Quarantine or mark permanent. Do not call the provider again automatically. Reconcile first. |

Do not classify every subscriber exception as transient. For database-only work, retry is safe only when its inbox transaction rolled back fully.

## Workflow

1. Freeze the exact producer payload in a contract test.
2. Create a typed carrier with identical nesting, field names, scalar types, and nullability.
3. Create one external subscriber in the consuming context.
4. Register event, subscriber, carrier, and version.
5. Keep database work inside the inbox transaction or apply the external-effect protocol.
6. Add producer-to-carrier, registry, success, duplicate, invalid, and classified-failure tests.
7. Register old and new carrier versions together.
8. Deploy the consumer that reads both versions.
9. Start the producer's new version only after consumer readiness succeeds.
10. Retain the old carrier until the maximum queue retention and dead-letter retention periods both expire.
11. Remove the old version only after evidence shows no old messages remain.

Test both registry versions and both serialized payloads during the compatibility window.

For an external effect, also test provider idempotency-key propagation, duplicate delivery, active lease, expired lease, timeout after possible success, success followed by completion-record failure, and reconciliation without a duplicate effect.

## Verified Backendbase risks

- Registry identity is event name plus event version.
- Duplicate registry keys fail construction.
- The event manager rejects internal subscribers matched on the external path.
- The subscriber registry requires an explicit constructor, even when it is empty. It creates each subscriber through reflection and resolves dependencies by container type, then by parameter name. A subscriber-class container binding does not control construction.
- Backendbase version-one `EntryAdded` publishes a nested `command` object. Its registered carrier now preserves that released shape with typed outer and nested DTOs.
- `ProducerConsumerContractTest` maps the real producer arguments through the registered carrier and real dispatcher.
- SQS reject behavior needs an infrastructure redrive policy.
- `ExternalEffectInbox` blocks repeated calls after an unknown outcome, but current code does not reconcile that outcome automatically.

## Authorization boundary

Do not start a live consumer, replay a message, alter inbox records, provision queues, or deploy producer and consumer changes without explicit authorization.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit src/Backendbase/Domain/ExampleCatalog/Tests/ServiceProviderTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/InMemoryExternalIntegrationEventRegistryTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcherTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ProducerConsumerContractTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessorTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInboxTest.php
composer phpstan
composer complexity
composer cs-check
```

Run the target project's new subscriber and carrier tests first. The listed Backendbase paths show verified registration and dispatch roles. Keep an end-to-end test that maps the actual producer payload into the registered carrier.

## Completion report

Report producer name and version, exact carrier fields, source service, subscriber effect type, registry key, inbox behavior, tests, rollout order, and external actions not performed.

## Provenance

Verified against current source on 2026-09-23:

- `src/Backendbase/Shared/Domain/Messaging/EventMessage.php`
- `src/Backendbase/Shared/Domain/Messaging/ExternalIntegrationEventSubscriber.php`
- `src/Backendbase/Domain/ExampleCatalog/Contracts/ExternalIntegrationEvents/V1/EntryAddedMessage.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/ExternalIntegrationEventSubscribers/ExampleCatalog/EntryAddedExternalSubscriber.php`
- `src/Backendbase/Domain/ExampleCatalog/ServiceProvider.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcher.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineInboxMessageTransaction.php`
- `src/Backendbase/Shared/Persistence/ExternalEffectInbox.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInbox.php`
- `tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcherTest.php`
- `tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessorTest.php`
- `tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInboxTest.php`
- `resources/platform/06-integration-event-consumers.md`
- `resources/platform/12-messaging-consumers.md`
- `resources/docs/3-integration-events.html`
