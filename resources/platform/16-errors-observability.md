# Errors and Observability

Translate failures at architectural boundaries. Keep public error bodies stable and diagnostic detail internal.

## HTTP failure ownership

- `Action` converts known `ProblemDetailsException` values into `application/problem+json`.
- The stable core contains `type`, `code`, `title`, `status`, and `detail`.
- `HttpErrorHandler` maps Slim and unexpected exceptions.
- `ShutdownHandler` logs fatal PHP errors and emits a structured 500 response.
- Stage, CI, production, and unknown environments hide internal exception details.

Monolog uses channel `backendbase-app`. It writes `extra.trace_id` from `X-Request-Id` or a generated UUIDv7.

Log exception type, stable operation, message ID, and trace ID. Do not log credentials, tokens, notification content, or unnecessary personal data.

## Health signals

- `GET /_status` checks process liveness.
- `GET /_status/ready` checks MySQL, Redis, the selected queue, and object storage.
- `outbox:status` checks pending and retried publication state.
- Delivery records and dead-letter queues show consumer failures.

No metrics, trace exporter, dashboard, or alert configuration exists in the repository.

Basis: `resources/docs/11-error-handling-and-observability.html`.
