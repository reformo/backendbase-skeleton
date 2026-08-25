# Backendbase notification-delivery pattern

## Role-to-target map

| Role | Backendbase component | Target equivalent |
| --- | --- | --- |
| Queue envelope parser | `NotificationMessageProcessor` | Target consumer boundary |
| External-effect claim | `ExternalEffectInbox` | Target idempotency store |
| Delivery port | `Notify` and `StackNotifier` | Target provider registry |
| Failure classifier | `QueueMessageFailurePolicy` | Target retry and dead-letter policy |
| Outcome | `QueueMessageHandlingOutcome` | Target acknowledge, retry, or reject result |

## Outcome table

| Condition | Backendbase outcome |
| --- | --- |
| Valid first delivery succeeds | Mark success and acknowledge |
| Inbox reports an existing completed item | Acknowledge without another provider call |
| Another attempt is active | Retry |
| Provider call started but result is unknown | Permanent-failure policy |
| Invalid JSON or value with valid metadata | Permanent-failure policy |
| Missing topic or message ID | Reject |
| Other dependency or transport error | Transient-failure policy |

The core shape is:

```php
$externalEffectInbox->processOnce(
    $consumerName,
    $messageId,
    $eventName,
    static fn () => $notifier->notify($notification),
);
```

The inbox must distinguish completed, active, and unknown external outcomes. A database rollback cannot prove that a provider did not send a message.

## Contract checks

- Validate envelope metadata before using it in failure-policy calls.
- Decode the body with exceptions enabled.
- Construct a complete notification model before inbox acquisition when construction has no external effect.
- Confirm that `StackNotifier` has a provider for the created type.
- Log stable operation, message ID, and exception class only.

## Current source limitations

- `NotificationMessageProcessor` always constructs an email notification.
- The inspected container registers only `SnsNotifier`, which supports SMS.
- The processor creates an email with only HTML body populated, while other typed fields can remain uninitialized.
- New delivery code must close this producer-model-provider mismatch rather than copy it.

## Exact source provenance

- `src/Backendbase/Infrastructure/Adapters/Queue/NotificationMessageProcessor.php`
- `src/Backendbase/Shared/Persistence/ExternalEffectInbox.php`
- `src/Backendbase/Shared/Persistence/ExternalEffectInProgress.php`
- `src/Backendbase/Shared/Persistence/ExternalEffectOutcomeUnknown.php`
- `src/Backendbase/Shared/Integrations/QueueMessageFailurePolicy.php`
- `src/Backendbase/Shared/Integrations/Operation/QueueMessageHandlingOutcome.php`
- `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInbox.php`
- `tests/Infrastructure/Adapters/Queue/NotificationMessageProcessorTest.php`
- `tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInboxTest.php`
- `resources/platform/20-notifications.md`

Adapt the outcome names to the target queue while preserving the external-effect reasoning.
