# Errors and Observability

Translate failures at architectural boundaries. Keep public error bodies stable and diagnostic detail internal.

## HTTP failure ownership

- Domain errors contain only a message and safe context.
- `DomainErrorProblemDetailsMapper` owns each stable `type`, `code`, `title`, and `status` value.
- `EntryAlreadyExists` maps to a stable 409 Conflict response at the HTTP adapter.
- The Infrastructure HTTP error handler maps domain errors, Slim exceptions, and unexpected exceptions.
- `ShutdownHandler` logs fatal PHP errors and emits a structured 500 response.
- Stage, CI, production, and unknown environments hide internal exception details.

Monolog uses channel `backendbase-app`. It writes `extra.trace_id` from `X-Request-Id` or a generated UUIDv7.

The logger factory explicitly sets UTC. Formatted log records include the `+00:00` offset even when the process uses another time zone.

Log exception type, stable operation, message ID, and trace ID. Do not log credentials, tokens, notification content, or unnecessary personal data.

When an SQS handler throws, `SqsTransport` records safe exception, queue, and message identifiers. It does not record the message body or receipt handle. `SqsQueue` leaves the message unacknowledged for retry after the visibility timeout.

## Health signals

- `GET /_status` checks process liveness.
- `GET /_status/ready` checks MySQL, Redis, the selected queue, and object storage.
- `outbox:status` checks pending and retried publication state.
- Delivery records, SQS transport logs, and dead-letter queues show consumer failures.

No metrics, trace exporter, dashboard, or alert configuration exists in the repository.

Basis: `resources/docs/11-error-handling-and-observability.html`.
