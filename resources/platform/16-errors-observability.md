# Errors and Observability

Translate failures at architectural boundaries. Keep public error bodies stable and diagnostic detail internal.

## HTTP failure ownership

- Domain errors contain only a message and safe context.
- `DomainErrorProblemDetailsMapper` owns each stable `type`, `code`, `title`, and `status` value.
- `EntryAlreadyExists` maps to a stable 409 Conflict response at the HTTP adapter.
- The Infrastructure HTTP error handler maps domain errors, Slim exceptions, and unexpected exceptions.
- Problem Details retains its integer `status` field. It must equal the HTTP response status.
- The HTTP error handler synchronizes `ActionError` before serialization for Slim, domain, and unexpected exceptions.
- Slim exceptions use the HTTP response reason phrase as `title`, such as `Not Found` for 404.
- Derive transport titles from the configured response factory. Preserve domain-specific titles from the domain error mapper.
- Unexpected failures retain `Internal Server Error` with status 500.
- The Example catch-all returns the five-field Problem Details core with `type: about:blank` and `application/problem+json`.
- Successful response envelopes omit `statusCode`. This rule does not remove error `status` fields.
- `ShutdownHandler` logs fatal PHP errors and emits a structured 500 response.
- Stage, CI, production, and unknown environments hide internal exception details.

Monolog uses channel `backendbase-app`. It writes `extra.trace_id` from `X-Request-Id` or a generated UUIDv7.

The logger factory explicitly sets UTC. Formatted log records include the `+00:00` offset even when the process uses another time zone.

Log exception type, stable operation, message ID, and trace ID. Do not log credentials, tokens, notification content, or unnecessary personal data.

Domain listeners and integration event dispatchers log event names, versions, timestamps, and subscriber or message classes only.
Do not log event objects, event arguments, payload arrays, or business values, including at debug level.

When an SQS handler throws, `SqsTransport` records safe exception, queue, and message identifiers. It does not record the message body or receipt handle. `SqsQueue` leaves the message unacknowledged for retry after the visibility timeout.

## Health signals

- `GET /_status` checks process liveness.
- `GET /_status/ready` checks MySQL, Redis, the selected queue, and object storage.
- Readiness returns HTTP 200 with `status: ready` or HTTP 503 with `status: unavailable`.
- Readiness `checks` is a JSON object, including when no checks are registered.
- `outbox:status` checks pending and retried publication state.
- Delivery records, SQS transport logs, and dead-letter queues show consumer failures.

`MeasureRequest` records method, route pattern, status, and elapsed milliseconds. It excludes URLs, bodies, and credentials.
`SafeExceptionContext` excludes exception messages and call arguments from production HTTP logs.
`database:diagnostics` reads aggregate MySQL version, charset, collation, lock, buffer, data-size, and index-size metrics.
The repository has no trace exporter, dashboard, or alert backend.

Basis: `resources/docs/11-error-handling-and-observability.html`.

`Idempotency-Key` identifies an intended write independently of `X-Request-Id`. Retries can use different trace identifiers.
Replay excludes trace headers and stores safe failure responses without debug details. Never log request bodies, keys, or response ciphertext.
Monitor unresolved HTTP claims and storage failures. See [HTTP idempotency](operations/http-idempotency.md).
