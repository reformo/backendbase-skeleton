# External Services and Object Storage

Define a narrow project-owned port for each external capability. Keep vendor Software Development Kit types inside infrastructure adapters.

## Adapter rules

- Identify the owning bounded context and exact external action.
- Validate configuration before the first request.
- Set finite connection and request timeouts.
- Validate remote responses before use.
- Translate recoverable vendor failures into stable project exceptions.
- Retry only transient and idempotent operations.
- Bound retries and use backoff.
- Keep credentials and personal data out of logs.
- Test request mapping, response mapping, and failures with doubles.

`BucketService` supports download URLs, local-file uploads, and signed browser POST uploads through the S3 adapter.

Prefer `CDN_BASE_URL` for downloads. Without it, the adapter creates a time-limited S3 request.

Set `OBJECT_STORE_ENDPOINT` for an S3-compatible service. When it is empty, object storage uses `AWS_ENDPOINT`. A custom endpoint uses path-style S3 requests.

Run `docker compose up -d` for local S3, SQS, SNS, SES, and CloudFront APIs. MiniStack starts in the default stack and saves its state. CloudFront distributions do not deliver content.

Nginx serves the configured MiniStack bucket at `http://127.0.0.1:8081/`. Set local `CDN_BASE_URL` to that URL with a trailing slash. Nginx accepts GET and HEAD requests and caches successful responses for 60 seconds. It binds to loopback only.

StackPort connects to MiniStack through `http://ministack:4566` and exposes a local resource browser at `http://127.0.0.1:8082`.

Run `composer message-viewer` on the host to inspect local SNS SMS messages and captured SES emails at `http://127.0.0.1:8083`.
Set `BACKENDBASE_ENV=dev` in the process environment or `.env`. Composer rejects other modes before startup, and viewer requests return HTTP 403 outside `dev`.
The viewer uses the project PHP runtime and Composer dependencies. Docker access is required for SMS logs.
Use `php -S 127.0.0.1:8084 bin/dev/message-viewer.php` to change its loopback port.
It reads the latest 2,000 log lines from `backendbase-ministack` and returns at most 200 SMS records.
It reads captured emails from `/_ministack/ses/messages` on loopback, using `BACKENDBASE_DEV_AWS_PORT` from `.env` or port 4566.
It shows up to 200 emails across captured accounts, newest first. Select an email to expand its headers and preview.
The viewer refreshes every five seconds. Provider reads have five-second timeouts and an 8 MiB size limit.
MiniStack logs up to 80 characters per SMS. The viewer displays the first line and does not retain messages independently.
Container recreation and log rotation can remove messages. The viewer does not send notifications or expose other container logs.
Some raw emails lack stored recipients. The viewer marks them as unavailable without guessing an address.
Email previews use stored HTML or plain text. An HTML allowlist and an isolated browser frame block active content and external requests.
Attachments are unavailable through the capture endpoint. The viewer does not capture SMTP, Twilio, or Netgsm deliveries.

S3 operations use at most three attempts for transient failures. Multipart retries preserve the upload and part identity.
Access denial and other permanent failures stop immediately. Failed multipart transfers are not restarted indefinitely.
Download signing preserves the object content type. Signed POST policies require the exact key and content type.
Signing expiry must be between 1 and 604800 seconds. Temporary credentials can expire earlier.
A missing or unreadable local upload file fails before a provider request.

Basis: `resources/docs/14-external-services-and-object-storage.html`.
