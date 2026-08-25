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

Current S3 limits include an unbounded multipart retry, forced JPEG download content type, unenforced upload content type, and no compatible-service endpoint setting.

Basis: `resources/docs/14-external-services-and-object-storage.html`.
