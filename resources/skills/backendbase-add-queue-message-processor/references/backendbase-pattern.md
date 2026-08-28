# Backendbase queue message processor pattern

A processor sits between the transport-normalized message and application work. It validates metadata, selects the correct idempotency model, classifies failures, and returns one explicit transport outcome.

## Target discovery

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, queue port, incoming mapper, outcome enum, failure policy, inbox contracts, processor tests, and nearest application port.
3. Determine whether work is a database mutation, pure computation, or an external provider effect.
4. Identify the stable consumer name and message ID sources.
5. Record every broker's ACK, RETRY, and REJECT behavior.

Do not copy Backendbase queue names, notification payloads, event names, provider configuration, or fixture IDs.

## Normalized message contract

Backendbase transports normalize these roles in a typed `Message` object:

| Field | Purpose |
| --- | --- |
| `body()` | event type or processor-specific body |
| `eventVersion()` | versioned event schema, when applicable |
| `data()` | decoded object payload |
| `id()` | idempotency identity |
| `destination()` | consumer identity, usually the resolved queue name |
| `routingKey()` | routing key or logical tag |

Discover the target envelope. Do not add unused fields only to imitate Backendbase.

## Role to target mapping

| Role | Backendbase reference | Target responsibility |
| --- | --- | --- |
| Processor | `ExternalIntegrationEventMessageProcessor` | Validate, transact, dispatch, classify. |
| Special effect processor | `NotificationMessageProcessor` | Use external-effect leasing. |
| Outcome | `QueueMessageHandlingOutcome` | Return acknowledge, retry, or reject. |
| Database deduplication | `InboxMessageTransaction` | Commit database work and completion together. |
| Provider deduplication | `ExternalEffectInbox` | Claim, call, and record with unknown-outcome handling. |
| Attempt tracking | `QueueMessageFailurePolicy` | Persist and bound transient failures. |

## Processing-mode decision

### Database mutation

Use when all required work can join the inbox database transaction. A duplicate acknowledges without repeated work. A failure rolls back the inbox insert and mutation.

### External effect

Use for provider calls that cannot join a database transaction. Claim before calling the provider. If the call or completion recording fails, treat the result as unknown and reject automatic replay. Reconcile through provider records and the message ID.

### Pure computation

Use no inbox only when repeating the computation has no externally visible effect and no durable result is required. State this assumption in tests and the completion report.

## Small processor skeleton

Names are illustrative. The failure policy calls must accept validated strings.

```php
public function process(Message $message): QueueMessageHandlingOutcome
{
    $consumerName = $message->destination();
    $messageId    = $message->id();

    try {
        $this->validateMetadata($consumerName, $messageId);
        $applicationMessage = $this->mapper->map($message->data());
        $this->inbox->processOnce(
            $consumerName,
            $messageId,
            $applicationMessage->type(),
            fn (): void => $this->handler->handle($applicationMessage),
        );
        $this->failurePolicy->succeeded($consumerName, $messageId);

        return QueueMessageHandlingOutcome::ACKNOWLEDGE;
    } catch (InvalidMessage $exception) {
        return $this->failurePolicy->permanentFailure(
            (string) $consumerName,
            (string) $messageId,
            $exception::class,
        );
    } catch (Throwable $exception) {
        return $this->failurePolicy->transientFailure(
            is_string($consumerName) ? $consumerName : '',
            is_string($messageId) ? $messageId : '',
            $exception::class,
        );
    }
}
```

When metadata is absent, reject without attempting a persistent key that cannot be trusted. Avoid empty-string identities unless the target failure policy explicitly supports them.

## Failure decision table

| Condition | Processor outcome |
| --- | --- |
| Success or processed duplicate | acknowledge |
| Missing or malformed required metadata | reject |
| Unknown event version or mapping failure | reject |
| Active external-effect claim | retry |
| Unknown external-effect result | reject and reconcile |
| Unexpected application or infrastructure failure | retry until bounded limit |
| Bounded limit reached | reject and mark terminal |

Backendbase uses five attempts for transient consumer failures. Select the target value with the broker redrive policy.

## Workflow

1. Freeze the normalized input contract.
2. Define metadata validation and message mapping.
3. Choose database, external-effect, or pure mode.
4. Define permanent and transient exception categories.
5. Implement one processor with one outcome path per category.
6. Record success by clearing prior failure evidence.
7. Add tests for success, duplicate, malformed input, failure policy, retry exhaustion, and effect claims.
8. Keep broker-specific acknowledge calls in the transport adapter.

## Verified Backendbase limits and risks

- Integration mapping errors and missing metadata are permanent.
- Ordinary throwables use the transient failure policy.
- The persistent policy rejects after attempt five.
- Database inbox identity is `(consumer_name, message_id)`.
- External-effect claims use 300 seconds in the reference.
- RabbitMQ RETRY immediately requeues.
- SQS RETRY and REJECT both leave the message until visibility or redrive acts.
- The reference notification processor builds email work, while the current container registers only an SMS notifier. Do not use it as proof of a complete provider path.

## Diagnostic safety

Log stable operation, exception class, message ID, consumer name, and trace ID when available. Do not log credentials, complete payloads, notification content, or unnecessary personal data.

## Authorization boundary

Do not consume, publish, replay, delete, or alter live messages while implementing or testing without explicit authority.

## Verification

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessorTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/NotificationMessageProcessorTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineInboxMessageTransactionTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInboxTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineQueueMessageFailurePolicyTest.php
composer phpstan
composer cs-check
```

Replace or extend paths with the target processor tests.

## Completion report

Report normalized metadata, processing mode, idempotency key, permanent and transient categories, retry limit, broker outcomes, redacted log fields, tests, and operator reconciliation needs.

## Provenance

Verified on 2026-08-25 from:

- `src/Backendbase/Shared/Integrations/Operation/QueueMessageHandlingOutcome.php`
- `src/Backendbase/Shared/Integrations/QueueMessageFailurePolicy.php`
- `src/Backendbase/Shared/Persistence/InboxMessageTransaction.php`
- `src/Backendbase/Shared/Persistence/ExternalEffectInbox.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/NotificationMessageProcessor.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineInboxMessageTransaction.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInbox.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineQueueMessageFailurePolicy.php`
- `resources/platform/12-messaging-consumers.md`
- `resources/docs/4-messaging-and-queues.html`
