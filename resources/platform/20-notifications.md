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

Identity contact verification sends email and SMS synchronously after its database transaction commits.
Each contact code generation has one delivery identity. Unknown outcomes require an explicit new generation.
See [contact verification](operations/contact-verification.md).

For local SNS and SES delivery, run `composer message-viewer` and open `http://127.0.0.1:8083`.
Select SMS or Emails. Expand an email to preview its stored HTML or plain text and inspect its headers.
This PHP development tool reads existing MiniStack captures. It does not change notification providers or send notifications.
MiniStack records up to 80 characters per SMS. The viewer shows the first line, recipient, and time with automatic refresh and filtering.
Some raw emails have no stored recipient. Previews block scripts, links, forms, and remote images. Attachments are unavailable.
See [local service limits](19-external-services.md).

No notification queue consumer is registered. Add one only after the message supplies every required field and the container registers a compatible provider.

Queued notification delivery must use `ExternalEffectInbox`. Treat an unknown provider outcome as permanent until an operator proves whether delivery occurred.

Use provider doubles in tests. Never send live email, push, or SMS from automated tests.

Do not log notification payloads because they can contain personal or sensitive content.

Basis: `resources/docs/13-notifications.html`.
