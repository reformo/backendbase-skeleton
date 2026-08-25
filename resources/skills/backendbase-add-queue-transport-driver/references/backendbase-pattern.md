# Backendbase queue transport-driver pattern

This reference adds a vendor transport behind an existing project-owned queue port. It preserves a common message envelope and explicit delivery outcomes.

## Target discovery

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, the queue port, current adapters and mappers, settings, dependency selection, readiness checks, tests, and deployment infrastructure.
3. Read official vendor documentation for acknowledgment, timeout, durability, ordering, retry, and dead-letter behavior.
4. Identify resources owned by application code and resources owned by infrastructure.
5. Record the target's normalized incoming and outgoing message contracts.

Do not copy Backendbase namespaces, queue or exchange names, hosts, credentials, AWS regions, endpoints, retention values, or default driver selection.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Transport-neutral port | `BackendbaseQueue` | Reuse the target project-owned interface. |
| Outgoing mapper | RabbitMQ and SQS mappers | Preserve logical body, version, data, and message ID. |
| Incoming mapper | normalized array | Make the processor transport-independent. |
| Driver adapter | `RabbitMQ` or `SqsQueue` | Keep vendor SDK types in infrastructure. |
| Selector | `config/dependencies/queue.php` | Validate one explicit driver value. |
| Readiness | transport-specific check | Use a bounded, read-only probe. |
| Tests | adapter and dependency tests | Prove semantics without live resources. |

## Queue port

Backendbase uses an array-based port:

```php
interface ApplicationQueue
{
    /** @param array<string, mixed> $params */
    public function publish(array $params): mixed;

    /**
     * @param array<string, mixed> $params
     * @param callable(array<string, mixed>): QueueMessageHandlingOutcome $handler
     */
    public function consume(array $params, callable $handler): void;
}
```

Use a more strongly typed existing target port when available. Do not replace it only to match this reference.

## Normalized envelope

Map vendor data into target equivalents of:

- logical message body or event type;
- event version when applicable;
- decoded object payload;
- stable producer message ID;
- consumer or queue identity;
- logical routing tag;
- transport attributes.

The producer message ID must survive transport mapping because the inbox uses it for idempotency.

## Outcome semantics

For each new driver, define and test:

| Outcome | Required question |
| --- | --- |
| Acknowledge | What irreversible broker action removes delivery? |
| Retry | When and how will delivery return? Is there a delay? |
| Reject | How does delivery become terminal? Does the application or broker move it? |
| Handler throws | Which safe outcome applies? |

Backendbase RabbitMQ acknowledges, requeues RETRY immediately, and rejects without requeue to a declared dead-letter route. Backendbase SQS deletes only ACK; RETRY and REJECT remain until visibility and redrive policies act.

## Small adapter decision skeleton

```php
$outcome = $handler($mapper->incoming($vendorMessage, $consumerName));

if ($outcome === QueueMessageHandlingOutcome::ACKNOWLEDGE) {
    $this->acknowledge($vendorMessage);

    return;
}

if ($outcome === QueueMessageHandlingOutcome::REJECT) {
    $this->reject($vendorMessage);

    return;
}

$this->retry($vendorMessage);
```

Each private operation must implement the vendor's exact semantics. Do not treat no-op reject as terminal unless infrastructure guarantees redrive.

## Configuration and dependency selection

1. Add a focused shared configuration provider for connection and transport behavior.
2. Parse integers, floats, and booleans explicitly.
3. Validate credentials as a complete set.
4. Set finite connection, read, write, and polling timeouts.
5. Add a focused dependency provider for the vendor client and adapter.
6. Extend the queue selector with one documented driver value.
7. Resolve the selected adapter in a dependency-definition test.
8. Add a readiness check that closes temporary resources.

Keep secrets outside source and logs. Use neutral values in local examples.

## Workflow

1. Freeze the target normalized envelope and outcome semantics.
2. Add the vendor package only when the user approved dependency changes.
3. Implement outgoing and incoming mapping as small deterministic classes.
4. Implement publish durability and route resolution.
5. Implement consume polling or callback flow and each outcome.
6. Add configuration, client factory, selector, and readiness.
7. Test malformed vendor payloads, missing IDs, missing receipt handles, timeouts, cleanup, and resource closure.
8. Document infrastructure-owned queues, dead-letter resources, retention, redrive, permissions, and monitoring.

## Invariants and risks

- Vendor SDK types stay in infrastructure.
- One stable message ID reaches every processor.
- Publishing uses durable settings available from the vendor when the contract requires durability.
- Resource cleanup runs on success and failure.
- Poll bounds must obey vendor limits.
- Outbox publication in Backendbase uses the configured default route. Per-event routing is not stored in each outbox row.
- A new driver changes readiness and deployment configuration.

## Authorization boundary

Adding source and tests does not authorize installing a package from the network, creating queues, changing IAM or broker permissions, publishing messages, or probing a live broker. Request permission for each external mutation.

## Verification

```sh
vendor/bin/phpunit tests/Shared/Integrations/RabbitMQTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/SqsQueueTest.php
vendor/bin/phpunit tests/Infrastructure/Health
vendor/bin/phpunit tests/Architecture
composer phpstan
composer cs-check
```

Run the target project's new driver, mapper, dependency, and readiness tests first. The listed Backendbase paths show verified adapter and readiness test roles. Use vendor client doubles. Do not send test messages to a shared broker.

## Completion report

Report driver value, package, normalized envelope, ACK/RETRY/REJECT semantics, timeouts, durability, readiness, infrastructure prerequisites, tests, and live actions not performed.

## Provenance

Verified on 2026-08-25 from:

- `src/Backendbase/Shared/Integrations/BackendbaseQueue.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/RabbitMQ.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/RabbitMQ/RabbitMQMessageMapper.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/RabbitMQ/RabbitMQTopology.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/SqsQueue.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/SqsMessageMapper.php`
- `config/dependencies/queue.php`
- `config/autoload/rabbitmq.global.php`
- `config/autoload/aws.global.php`
- `src/Backendbase/Infrastructure/Health/RabbitMQReadinessCheck.php`
- `src/Backendbase/Infrastructure/Health/SqsReadinessCheck.php`
- `tests/Shared/Integrations/RabbitMQTest.php`
- `tests/Infrastructure/Adapters/Queue/SqsQueueTest.php`
- `resources/platform/13-queue-runtime.md`
- `resources/docs/4-messaging-and-queues.html`
