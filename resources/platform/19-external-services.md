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

S3 operations use at most three attempts for transient failures. Multipart retries preserve the upload and part identity.
Access denial and other permanent failures stop immediately. Failed multipart transfers are not restarted indefinitely.
Download signing preserves the object content type. Signed POST policies require the exact key and content type.
Signing expiry must be between 1 and 604800 seconds. Temporary credentials can expire earlier.
A missing or unreadable local upload file fails before a provider request.

Basis: `resources/docs/14-external-services-and-object-storage.html`.
