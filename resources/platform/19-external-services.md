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

Run `docker compose --profile integration up -d` for local S3, SQS, and SNS endpoints. The Moto service is stateless and does not start in the default stack.

Current S3 limits include an unbounded multipart retry, forced JPEG download content type, and an unenforced upload content type.

Basis: `resources/docs/14-external-services-and-object-storage.html`.
