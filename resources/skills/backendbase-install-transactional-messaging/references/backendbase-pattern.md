# Backendbase transactional messaging foundation

This reference is for a target project that lacks an equivalent transactional outbox and inbox. If the target already has one, extend its established design instead of installing a second runtime.

## Target discovery

Resolve only unknown facts needed by the affected behavior. Reuse verified facts while their sources remain unchanged.

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, architecture tests, database and transaction APIs, migration tool, queue ports, container, console bootstrap, clocks, identifier types, and test database support.
3. Search for existing outbox, inbox, message log, relay, deduplication, and failure-policy code.
4. Identify which database owns the business transaction.
5. Identify all supported queue transports and their acknowledgment models.

Do not assume the `Backendbase\` namespace, Doctrine, MySQL, UUIDv7, table names, command names, queue names, hosts, or configuration keys.

## Role to target mapping

| Role | Backendbase reference | Target responsibility |
| --- | --- | --- |
| Producer contract | `IntegrationEvent` | Stable name, version, time, and JSON object. |
| Atomic write port | `IntegrationEventTransaction` | Commit business work, local subscribers, and optional outbox publication together. |
| Transaction adapter | `DoctrineIntegrationEventTransaction` | Run business work and dispatch its returned event before commit. |
| Outbox writer | `IntegrationEventOutbox` and `DoctrineIntegrationEventOutbox` | Append to the active business transaction when queue delivery is selected. |
| Relay application service | `OutboxRelayService`, `OutboxPublication`, `OutboxRetryPolicy` | Orchestrate publication and calculate claim and retry deadlines from clock readings. |
| Current time | `Shared/Time/Clock` | Supply a UTC instant for scheduling and retention. Reuse the target clock contract. |
| Outbox persistence adapter | `DoctrineOutboxMessageStore` | Claim rows and apply supplied publication or failure state. |
| Publisher | `OutboxMessagePublisher` | Map an outbox row to the project queue port. |
| Database inbox | `InboxMessageTransaction` | Deduplicate and commit database work atomically. |
| External-effect inbox | `ExternalEffectInbox` | Lease non-transactional provider effects. |
| Failure application service | `QueueMessageFailureService` | Decide permanent and bounded transient outcomes. |
| Failure persistence adapter | `DoctrineQueueMessageFailureStore` | Record, mark, and clear supplied failure state. |
| Operations | relay, status, cleanup commands | Expose a finite or continuous relay and finite maintenance work. |

## Minimal project-owned contracts

Keep framework and vendor types out of these interfaces:

```php
interface IntegrationEventTransaction
{
    /** @param callable(): IntegrationEvent $transactionalWork */
    public function execute(callable $transactionalWork): void;
}

interface InboxMessageTransaction
{
    /** @param callable(): void $databaseMutation */
    public function processOnce(
        string $consumerName,
        string $messageId,
        string $eventName,
        callable $databaseMutation,
    ): void;
}

enum QueueMessageHandlingOutcome
{
    case ACKNOWLEDGE;
    case RETRY;
    case REJECT;
}
```

Adapt names to the target's established vocabulary.

## Reference schema responsibilities

Backendbase uses three controls:

| Record | Identity | Required state |
| --- | --- | --- |
| Outbox | event message ID | name, version, JSON payload, occurred and created time, availability, published time, attempts, last error, claim token, claim expiry |
| Inbox | consumer name plus message ID | event name, received time, processed time, external-effect claim token and expiry |
| Delivery failure | consumer name plus message ID | attempts, last failure type and time, terminal time |

Create migrations in the target's current migration namespace and style. Preserve snake_case database names. Review indexes for pending relay scans, processed cleanup, active claims, and terminal failures.

Do not apply migrations until the user authorizes the exact target database and migration command.

## Producer transaction

In unmodified Backendbase, the transaction dispatches the callback's returned event before commit. Adapt the event manager and transaction names to the target:

```php
$connection->transactional(static function () use ($transactionalWork, $eventManager): void {
    $event = $transactionalWork();
    $eventManager->dispatchEvent($event);
});
```

The event manager runs local subscribers for both `DELIVER_VIA_QUEUE` values. A true flag then appends one outbox row, including when no local subscriber exists. Direct producer dispatch requires an active transaction. Keep one outbox insertion owner, and keep consumer dispatch separate so received events are not republished.

Allowed callback and local subscriber work is authoritative database reads and database mutation on the shared connection. The callback returns the event after required state is known. Network, process, filesystem, and direct broker publication are not allowed. A failure rolls back business writes, subscriber writes, and outbox writes together.

## Relay state machine

1. Select the oldest unpublished row whose availability and claim permit work.
2. Claim it in a short database transaction.
3. Publish outside the claim transaction.
4. On success, set `published_at` only when the claim token still matches.
5. On failure, increment attempts, record a safe failure code, clear the claim, and delay availability.

Backendbase uses a 60-second claim and a delay of `2 ** min(attempts, 8)` seconds. It has no terminal publication limit. Treat these as verified reference values, not universal values. Select target values from broker latency, worker concurrency, and operations requirements.

In unmodified Backendbase, `OutboxPublication` reads the clock when claiming and again after publication. The outbox writer uses the same clock contract for initial availability. Failure and inbox adapters use it for timestamps consumed by retention. Test deadlines with a fixed clock, including time spent publishing. Adapt the contract and placement to the target.

A broker publish can succeed before the database marks the row. The relay can publish the event again. Consumers must be idempotent.

## Consumer paths

### Database mutation

Insert the inbox row, run the subscriber database mutation, and set `processed_at` in one transaction. A duplicate `(consumer_name, message_id)` returns success without repeated work. A failed mutation rolls back the inbox insert so delivery can retry.

### External provider effect

The provider call cannot join the database transaction. Claim the inbox record, commit the claim, call the provider, and then record completion. If the provider call or completion write fails, the outcome can be unknown. Do not retry automatically. Reconcile through provider records and the message ID.

Backendbase uses a 300-second external-effect claim. `DoctrineExternalEffectInbox` reads the UTC clock and delegates storage to `Doctrine/Inbox/ExternalEffectClaims`. A claim equal to the current instant remains active. An expired incomplete attempt has an unknown outcome and must not repeat the provider effect. Select the target lease and comparison rules from provider and network behavior.

## Failure classification

| Failure | Outcome |
| --- | --- |
| Missing required metadata | reject |
| Unknown contract version | reject |
| Payload mapping error | reject |
| Unsupported subscriber type | reject |
| Ordinary subscriber or infrastructure exception | retry until target limit |
| External effect already active | retry later |
| External effect outcome unknown | reject and reconcile |

Backendbase records a terminal transient failure on attempt five. Align the application limit with broker redrive behavior when possible.

## Workflow

1. Add project-owned contracts and operation result types.
2. Add migrations and migration tests or schema setup for isolated database tests.
3. Implement atomic producer storage and rollback tests.
4. Implement relay claims, publication mapping, success marking, and retry tests.
5. Implement database inbox deduplication and rollback tests.
6. Add external-effect leasing only when a provider consumer needs it.
7. Implement persistent failure tracking.
8. Add relay, health, and cleanup commands. Backendbase supports finite and continuous relay modes; health and cleanup remain finite.
9. Register ports and adapters through the target container.
10. Update architecture, configuration, operations, and deployment documentation.

## Invariants and risks

- The system provides at-least-once publication, not exactly once.
- Never make a network call inside the business transaction.
- Never acknowledge before durable required work completes.
- Preserve old versioned carriers while old messages can exist.
- Do not manually delete inbox evidence during ordinary recovery.
- Keep payloads and credentials out of error logs.
- SQS-like transports can require an external redrive policy for reject behavior.
- RabbitMQ-like immediate requeue can exhaust application attempts quickly.

## Authorization boundary

Generating migrations, commands, and configuration is in scope. Applying migrations, starting consumers, publishing messages, provisioning brokers, installing schedules, or changing live message records requires explicit authorization.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run focused tests for each layer, then:

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue
vendor/bin/phpunit tests/Infrastructure/Adapters/Console/Queue
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
composer test
```

Adapt test paths and database setup to the target. Tests must cover both sides of every atomic boundary.

## Completion report

Report contracts, schema and indexes, claim and retry values, exactly-once disclaimer, operations commands, migrations created but not applied, tests, required live checks that could not run, and required broker or scheduler work.

## Provenance

Verified against current source on 2026-09-23:

- `src/Backendbase/Shared/Persistence/IntegrationEventTransaction.php`
- `src/Backendbase/Shared/Persistence/InboxMessageTransaction.php`
- `src/Backendbase/Shared/Persistence/ExternalEffectInbox.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationEventTransaction.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationEventOutbox.php`
- `src/Backendbase/Shared/Persistence/Outbox/IntegrationEventOutbox.php`
- `src/Backendbase/Application/Messaging/OutboxRelayService.php`
- `src/Backendbase/Application/Messaging/OutboxPublication.php`
- `src/Backendbase/Shared/Time/Clock.php`
- `config/dependencies/time.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/Inbox/ExternalEffectClaims.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineOutboxMessageStore.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineInboxMessageTransaction.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInbox.php`
- `src/Backendbase/Application/Messaging/QueueMessageFailureService.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineQueueMessageFailureStore.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/OutboxMessagePublisher.php`
- `resources/database/Migrations/Version20260823000000.php`
- `resources/platform/11-messaging-outbox.md`
- `resources/platform/12-messaging-consumers.md`
- `resources/docs/3-integration-events.html`
- `resources/docs/4-messaging-and-queues.html`
