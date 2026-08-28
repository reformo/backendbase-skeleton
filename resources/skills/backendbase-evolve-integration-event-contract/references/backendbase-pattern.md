# Backendbase integration-event evolution pattern

This reference protects released event names, versions, and payloads while producers and consumers move independently.

## Target discovery

1. Read all applicable `AGENTS.md` files.
2. Inspect Composer autoloading, producer events, payloads, carriers, registry entries, subscribers, outbox rows, queue retention and dead-letter rules, deployments, and contract tests.
3. Search all repositories available in scope for the event name and version.
4. Establish whether messages can remain in an outbox, queue, dead-letter store, replay archive, or provider integration.
5. Record the current serialized producer payload. Do not infer it from constructor fields alone.

Do not copy the Backendbase example service name, version-one payload, namespaces, queue identifiers, or fixture IDs.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Stable identity | producer `EVENT_TYPE` | Preserve for the same business fact. |
| Producer schema | `Contracts/IntegrationEvents/V{n}` | Keep released classes unchanged. |
| Consumer schema | `Contracts/ExternalIntegrationEvents/V{n}` | Retain one carrier per accepted version. |
| Registry key | event name plus version | Reject duplicates and unknown versions. |
| Rollout proof | producer-to-carrier mapping test | Use the actual serialized payload. |
| Retention decision | queue and outbox operations | Keep old code while old messages can exist. |

## Compatibility classification

Usually compatible within a released version:

- documentation clarification with no wire change;
- internal implementation change that preserves exact serialized values and types.

Usually incompatible:

- adding a required field;
- removing or renaming a field;
- changing nesting;
- changing scalar or nullability rules;
- changing semantic units;
- changing the event name.

When uncertain, create a new version. Do not rely on a permissive mapper to define compatibility.

## Versioned registration example

The names are illustrative. Both carriers remain registered while version-one messages can exist.

```php
[
    'events' => InvoiceIssuedExternalSubscriber::getSubscribedEvents(),
    'subscriberFQCN' => InvoiceIssuedExternalSubscriber::class,
    'messageFQCN' => InvoiceIssuedV1Message::class,
    'eventVersion' => '1.0',
],
[
    'events' => InvoiceIssuedExternalSubscriber::getSubscribedEvents(),
    'subscriberFQCN' => InvoiceIssuedExternalSubscriber::class,
    'messageFQCN' => InvoiceIssuedV2Message::class,
    'eventVersion' => '2.0',
],
```

If the current Backendbase registry rejects duplicate subscriber entries for one event, adapt registration or use separate version-specific subscriber classes. Verify actual container behavior before coding.

## Producer-to-carrier proof

Use the same mapper as runtime:

```php
$payload = $event->getEventArguments();
$message = $mapper->map(InvoiceIssuedV2Message::class, $payload);

self::assertSame($event->invoiceId(), $message->invoiceId());
```

Test missing fields, extra fields, wrong scalar types, nullability, and the exact registry version.

## Safe rollout

1. Add the new carrier and consumer support first when producers and consumers deploy independently.
2. Deploy and verify consumer acceptance.
3. Change the producer to publish the new version.
4. Observe outbox, failures, and dead-letter state.
5. Keep old support through the maximum message-retention and recovery window.
6. Remove old support only through a separately reviewed change with operational evidence.

For breaking event-name changes, define dual publication or a controlled migration. Do not silently repurpose the old name.

## Verified Backendbase risks

- Registry identity is `eventName:eventVersion`.
- Missing metadata, unknown versions, no subscriber, wrong subscriber interface, and mapping errors are permanent failures.
- The current `NewExampleAdded` version-one producer publishes an outer `exampleId` plus a nested `command` object.
- Its registered version-one carrier preserves the same outer and nested shape.
- A real dispatcher contract test maps the producer arguments into the registered carrier. Preserve this evidence during contract changes.
- Old messages can survive in outbox, primary queue, and dead-letter storage.

## Authorization boundary

Do not purge queues, delete old outbox or inbox records, replay dead letters, change a live registry, or deploy producer and consumer revisions without explicit authority.

## Verification

```sh
vendor/bin/phpunit tests/Domain/ExampleBoundedContext/Contracts/IntegrationEvents
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/InMemoryExternalIntegrationEventRegistryTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcherTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessorTest.php
composer phpstan
composer cs-check
```

Run each newly added versioned contract test before this broader reference set. Add an integration test for every supported producer payload and carrier pair.

## Completion report

Report the compatibility decision, stable event identity, old and new versions, exact wire differences, rollout order, retention window, tests, observed risks, and operational actions not performed.

## Provenance

Verified on 2026-08-25 from:

- `src/Backendbase/Infrastructure/Adapters/Queue/InMemoryExternalIntegrationEventRegistry.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcher.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php`
- `config/dependencies/modules.php`
- `src/Backendbase/Domain/ExampleBoundedContext/ServiceProvider.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/IntegrationEvents/V1/NewExampleAddedPayload.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/ExternalIntegrationEvents/V1/NewExampleAddedMessage.php`
- `tests/Infrastructure/Adapters/Queue/InMemoryExternalIntegrationEventRegistryTest.php`
- `tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcherTest.php`
- `resources/platform/05-integration-event-contracts.md`
- `resources/platform/06-integration-event-consumers.md`
- `resources/docs/3-integration-events.html`
