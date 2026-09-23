# Notifications

Shared notification models define provider-independent email, push, and SMS requests. Infrastructure providers map those models to vendor calls.

## Models and providers

- Validate email addresses, attachments, push fields, and E.164 SMS numbers before provider calls.
- `Notify` accepts one email, push, or SMS notification directly. `StackNotification` groups several notifications when needed.
- `StackNotifier` selects one `NotificationProvider` by stable type. It checks every grouped type before delivery.
- Providers return `NotificationResult`. Repeated types retain each message ID in delivery order.
- `SnsNotifier` sends SMS by default. Set `BACKENDBASE_SMS_DRIVER=twilio` or `netgsm` to select another SMS provider.
- Twilio uses an account SID, auth token, and sender. Netgsm uses an API sub-user, password, registered sender, and encoding.
- All SMS providers accept E.164 numbers. Netgsm maps Turkish numbers to national format and other numbers to `00` format.
- A Twilio message SID or Netgsm job ID confirms provider acceptance, not delivery to the handset.
- Netgsm requests omit `iysfilter`, which Netgsm treats as informational traffic. Commercial messages need an explicit content and consent contract.
- `FirebasePushNotifier` sends push notifications when `FIREBASE_PROJECT_ID` enables its container registration.
- A relative push image path requires `CDN_BASE_URL`. An absolute HTTP or HTTPS image URL needs no base URL.
- `SesEmailNotifier` sends email by default. Set `BACKENDBASE_EMAIL_DRIVER=smtp` for `SmtpEmailNotifier`.
- The SES client disables automatic retries. A connection failure can leave the send outcome unknown; callers must check it before sending again.
- Email providers require a sender, recipient, subject, and rendered HTML body. Template metadata is not rendered.
- `NotificationBatchFailed` reports completed deliveries and the failed item index. Do not retry a partial batch without checking external outcomes.

No notification queue consumer is registered. Add one only after the message supplies every required field and the container registers a compatible provider.

Queued notification delivery must use `ExternalEffectInbox`. Treat an unknown provider outcome as permanent until an operator proves whether delivery occurred.

Use provider doubles in tests. Never send live email, push, or SMS from automated tests.

Do not log notification payloads because they can contain personal or sensitive content.

Basis: `resources/docs/13-notifications.html`.
