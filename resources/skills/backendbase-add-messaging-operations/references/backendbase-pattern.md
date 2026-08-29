# Backendbase messaging operations pattern

This reference adds finite operational controls around an existing transactional outbox and inbox. It does not install the messaging foundation or define an automatic dead-letter replay.

## Target discovery

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, outbox, inbox, delivery-failure records, operation ports, database adapters, console registration, deployment layout, logs, tests, scheduler, supervisor, and alerting.
3. Identify current data retention and regulatory requirements.
4. Determine how the platform prevents overlap and reports nonzero exits.
5. Record which message records are terminal and which remain recoverable.

Do not copy Backendbase command names, queue names, host paths, cron paths, retention values, alert endpoints, or database identifiers without target discovery.

## Role to target mapping

| Role | Backendbase reference | Target responsibility |
| --- | --- | --- |
| Relay operation | `OutboxRelay` | Publish a bounded number of available rows. |
| Health operation | `OutboxMonitor` | Report pending, retried, and oldest state. |
| Cleanup operation | `IntegrationMessageLogCleaner` | Delete only old terminal evidence. |
| Console adapter | relay, status, cleanup commands | Validate limits and expose useful exits. |
| Scheduler | external platform | Run finite commands and prevent overlap. |
| Worker supervisor | external platform | Own long-running consumers. |
| Alerts | external platform | Convert status failures into operator action. |

## Reference command behavior

Backendbase currently uses these verified limits:

| Operation | Reference limit | Failure exit |
| --- | --- | --- |
| Relay | 1 through 1000 rows | one or more publish failures |
| Status | positive maximum pending age | old pending row or any retried row |
| Cleanup | at least 30 retention days; 1 through 10000 rows per log | invalid input or cleanup failure |

These values are reference behavior, not portable defaults. Select target limits from transaction cost, backlog size, retention policy, and scheduler frequency.

## Relay operation

The relay must process the oldest available work, respect claims, publish outside the claim transaction, and report published and failed counts. A command can convert the result into an exit code:

```php
$result = $this->outboxRelay->relay($limit);
$output->writeln('Published: ' . $result->published() . '; failed: ' . $result->failed() . '.');

return $result->failed() > 0 ? self::FAILURE : self::SUCCESS;
```

Do not hide partial failure behind a success exit.

## Status operation

Expose safe operational state:

- pending message count;
- retried message count;
- oldest pending timestamp or age.

Return failure when the agreed service-level threshold is breached. Connect the nonzero exit to real monitoring; cron logging alone is not an alert.

## Cleanup operation

Delete only records that prove terminal completion and are older than the retention cutoff:

- outbox rows with old `published_at`;
- inbox rows with old `processed_at`;
- delivery failures with old terminal or dead-letter time.

Never delete pending, claimed, unprocessed, or nonterminal failure rows. Use bounded batches and stable indexes.

## Scheduling model

1. Run finite relay work frequently enough for the delivery objective.
2. Run status checks and send nonzero exits to monitoring.
3. Run cleanup at a lower frequency under an approved retention policy.
4. Use a platform lock or single-concurrency job for relay and cleanup.
5. Run consumers under a supervisor, not cron.

The Backendbase repository contains no scheduler, supervisor, dashboard, or alert definition. Generate platform-specific files only when the user selects the platform and approves the scope.

## Recovery safety

Use database and broker evidence together. Do not edit message-control rows during normal recovery. Manual deletion can remove idempotency evidence. Manual timestamp or claim changes can duplicate publication or external effects.

No automatic dead-letter replay exists in Backendbase. A replay needs a separate reviewed design that validates the fixed cause, target queue, version compatibility, duplicate risk, authorization, and audit record.

## Workflow

1. Define operation ports and typed result objects when missing.
2. Add indexed database queries with bounded limits.
3. Add console commands with strict boundary validation.
4. Test terminal selection, old and new cutoffs, empty state, partial failures, and exit codes.
5. Document scheduler frequency, overlap prevention, worker supervision, alert connection, and retention ownership.
6. Keep installation and live execution outside the code change unless separately authorized.

## Invariants and risks

- At-least-once publication means duplicate delivery remains possible.
- Backendbase outbox publication failures have no terminal attempt limit.
- Cleanup preserves incomplete and retryable records.
- Status output must not include payloads or secrets.
- A successful relay publish can precede a failed database mark.
- SQS redrive and RabbitMQ dead-letter state require broker-level observation.

## Authorization boundary

Do not install cron entries, create platform jobs, restart workers, replay messages, delete records, or run maintenance against a shared database without explicit authorization.

## Verification

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationMessageOperationsTest.php
vendor/bin/phpunit tests/Application/Messaging/OutboxRelayServiceTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineOutboxMessageStoreTest.php
vendor/bin/phpunit tests/Infrastructure/UseCase/Console/Queue/QueueMaintenanceCommandsTest.php
vendor/bin/phpunit tests/Infrastructure/UseCase/Console/Queue/ShowOutboxStatusTest.php
composer phpstan
composer complexity
composer cs-check
```

Run each finite command once only in an isolated or explicitly authorized environment.

## Completion report

Report operations, limits, retention, terminal predicates, exit conditions, schedule design, overlap control, monitoring gap, tests, and schedules or maintenance actions not performed.

## Provenance

Verified on 2026-08-25 from:

- `src/Backendbase/Shared/Integrations/OutboxRelay.php`
- `src/Backendbase/Shared/Integrations/OutboxMonitor.php`
- `src/Backendbase/Shared/Integrations/IntegrationMessageLogCleaner.php`
- `src/Backendbase/Application/Messaging/OutboxRelayService.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineOutboxMessageStore.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineOutboxMonitor.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationMessageLogCleaner.php`
- `src/Backendbase/Infrastructure/UseCase/Console/Queue/RelayOutboxMessages.php`
- `src/Backendbase/Infrastructure/UseCase/Console/Queue/ShowOutboxStatus.php`
- `src/Backendbase/Infrastructure/UseCase/Console/Queue/CleanupIntegrationMessages.php`
- `tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationMessageOperationsTest.php`
- `tests/Infrastructure/UseCase/Console/Queue/QueueMaintenanceCommandsTest.php`
- `resources/platform/11-messaging-outbox.md`
- `resources/platform/13-queue-runtime.md`
- `resources/docs/4-messaging-and-queues.html`
