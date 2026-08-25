# Backendbase notification-provider pattern

## Dispatch model

```text
Notification model -> StackNotification -> StackNotifier
-> provider selected by type -> vendor client -> project status
```

| Role | Backendbase state | Target decision |
| --- | --- | --- |
| SMS | Type `sms`; `SnsNotifier` is registered | Target model, type, and SMS vendor |
| Push | Type `push`; Firebase adapter is not registered | Target push vendor and registration |
| Email | Type `email`; no provider is registered | Target email vendor and complete model |
| Stack | Type `stack`; groups notifications | Target multi-channel dispatch need |

A provider follows this shape:

```php
final readonly class VendorEmailNotifier implements Notify
{
    public function type(): string
    {
        return 'email';
    }

    public function notify(Notification $notification): array
    {
        if (! $notification instanceof EmailNotification) {
            throw new UnexpectedValueException('Email notification required.');
        }

        return $this->mapResult($this->client->send($this->mapRequest($notification)));
    }
}
```

Register it in the target container and add it to `StackNotifier`. Test that each producer-created type resolves to a provider.

## Current source limitations

- The notification queue creates email work, but only the SMS provider is registered.
- The Firebase adapter exists without container registration.
- `EmailNotification` has mutable typed fields that can remain uninitialized.
- `PushNotification` does not fully enforce required target and body rules.
- Firebase debug logging includes the provider payload and can expose notification content.
- Do not reproduce these gaps in a new provider.

## Exact source provenance

- `src/Backendbase/Shared/Integrations/Notify.php`
- `src/Backendbase/Shared/Primitives/Notification/Notification.php`
- `src/Backendbase/Shared/Primitives/Notification/EmailNotification.php`
- `src/Backendbase/Shared/Primitives/Notification/PushNotification.php`
- `src/Backendbase/Shared/Primitives/Notification/SmsNotification.php`
- `src/Backendbase/Shared/Primitives/Notification/StackNotification.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/StackNotifier.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/SnsNotifier.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/FirebasePushNotifier.php`
- `config/dependencies/aws.php`
- `tests/Shared/Primitives/Notification/NotificationPrimitivesTest.php`
- `tests/Infrastructure/Adapters/Notification/StackNotifierTest.php`
- `tests/Infrastructure/Adapters/Notification/SnsNotifierTest.php`
- `tests/Infrastructure/Adapters/Notification/FirebasePushNotifierTest.php`
- `resources/docs/13-notifications.html`
- `resources/platform/20-notifications.md`

Provider response schemas and settings must come from the target vendor contract.
