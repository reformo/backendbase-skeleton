# Backendbase notification-provider pattern

## Dispatch model

```text
Notification model -> Notify/StackNotifier
-> provider selected by type -> vendor client -> project status
```

| Role | Backendbase state | Target decision |
| --- | --- | --- |
| SMS | Type `sms`; SNS is the default, with Twilio or Netgsm selectable | Target model, type, and SMS vendor |
| Push | Type `push`; Firebase registers when a project ID is configured | Target push vendor and registration |
| Email | Type `email`; SES is the default and SMTP is selectable | Target email vendor and complete model |
| Stack | Groups notifications; direct notifications also work | Target multi-channel dispatch need |

A provider follows this shape:

```php
final readonly class VendorEmailNotifier implements NotificationProvider
{
    public function type(): string
    {
        return 'email';
    }

    public function notify(Notification $notification): NotificationResult
    {
        if (! $notification instanceof EmailNotification) {
            throw new UnexpectedValueException('Email notification required.');
        }

        $providerResult = $this->client->send($this->mapRequest($notification));

        return NotificationResult::delivered('email', $this->messageId($providerResult));
    }
}
```

Register it in the target container and add it to `StackNotifier`. Test that each producer-created type resolves to a provider.
Do not expose the vendor client or vendor response through the project port.

## Current source limits

- No notification queue producer or consumer contract is registered.
- Firebase delivery requires a configured project and credentials.
- Email providers require rendered HTML. Template metadata is not rendered.
- A grouped delivery can fail after earlier items have been sent. Inspect `NotificationBatchFailed` before retrying.
- Do not log recipients, device tokens, message bodies, or attachments.

## Exact source provenance

- `src/Backendbase/Shared/Integrations/Notify.php`
- `src/Backendbase/Shared/Integrations/NotificationProvider.php`
- `src/Backendbase/Shared/Integrations/Operation/NotificationResult.php`
- `src/Backendbase/Shared/Primitives/Notification/Notification.php`
- `src/Backendbase/Shared/Primitives/Notification/EmailNotification.php`
- `src/Backendbase/Shared/Primitives/Notification/PushNotification.php`
- `src/Backendbase/Shared/Primitives/Notification/SmsNotification.php`
- `src/Backendbase/Shared/Primitives/Notification/StackNotification.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/StackNotifier.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/SnsNotifier.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/TwilioSmsNotifier.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/NetgsmSmsNotifier.php`
- `src/Backendbase/Infrastructure/Adapters/Notification/FirebasePushNotifier.php`
- `config/dependencies/aws.php`
- `tests/Shared/Primitives/Notification/NotificationPrimitivesTest.php`
- `tests/Infrastructure/Adapters/Notification/StackNotifierTest.php`
- `tests/Infrastructure/Adapters/Notification/SnsNotifierTest.php`
- `tests/Infrastructure/Adapters/Notification/TwilioSmsNotifierTest.php`
- `tests/Infrastructure/Adapters/Notification/NetgsmSmsNotifierTest.php`
- `tests/Infrastructure/Adapters/Notification/FirebasePushNotifierTest.php`
- `resources/docs/13-notifications.html`
- `resources/platform/20-notifications.md`

Provider response schemas and settings must come from the target vendor contract.
