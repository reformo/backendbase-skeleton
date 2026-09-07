# Notifications

Shared notification models define provider-independent email, push, and SMS requests. Infrastructure providers map those models to vendor calls.

## Models and providers

- Validate email addresses, attachments, push fields, and E.164 SMS numbers before provider calls.
- `StackNotifier` selects one registered provider by stable notification type.
- Providers return `NotificationResult`. They do not expose vendor clients or vendor response arrays.
- `SnsNotifier` sends SMS through Amazon Simple Notification Service (SNS).
- `FirebasePushNotifier` maps push requests, but container registration is absent.
- Email has a model, but no registered email provider exists.

No notification queue consumer is registered. Add one only after the message supplies every required field and the container registers a compatible provider.

Queued notification delivery must use `ExternalEffectInbox`. Treat an unknown provider outcome as permanent until an operator proves whether delivery occurred.

Use provider doubles in tests. Never send live email, push, or SMS from automated tests.

Do not log notification payloads because they can contain personal or sensitive content.

Basis: `resources/docs/13-notifications.html`.
