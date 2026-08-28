# Backendbase trace-aware logging pattern

This reference covers correlation fields in Monolog. It does not define distributed tracing, metrics, exporters, dashboards, or alerts.

## Target discovery

1. Read applicable `AGENTS.md` files.
2. Inspect Composer packages, PSR-4 roots, logger configuration and factory, HTTP bootstrap, error handlers, queue envelope, worker bootstrap, and logging tests.
3. Identify every existing request or message correlation field.
4. Select one canonical internal field and define its trust boundary.
5. Check whether adding propagation changes a public HTTP or versioned message contract.

Do not copy the Backendbase channel, header, path, service name, queue fields, or environment keys without discovery.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Correlation source | `X-Request-Id` or UUIDv7 | Reuse a validated target header or boundary identifier. |
| Logger processor | `config/dependencies/logger.php` | Add one canonical context field. |
| Logger structure | `config/autoload/logger.global.php` | Keep path, channel, and level target-specific. |
| Public error boundary | `HttpErrorHandler` | Keep internal trace data out of responses. |
| Queue propagation | Not implemented in current envelope | Treat any addition as a contract change. |

## Small implementation example

The header and field match Backendbase only. Adapt them after target discovery.

```php
$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
$traceId   = is_string($requestId) && $requestId !== ''
    ? $requestId
    : Uuid::uuid7()->toString();

$logger->pushProcessor(static function (LogRecord $record) use ($traceId): LogRecord {
    $record->extra['trace_id'] = $traceId;

    return $record;
});
```

Generate the identifier once for the operation. Do not generate a different value in each internal layer.

## Workflow

1. Define the boundary that accepts or creates the identifier.
2. Validate length and allowed format when the value is externally supplied.
3. Attach the canonical value through one logger processor.
4. Use structured fields for operation name, exception class, message identifier, and safe resource identifier.
5. Review all new log fields for secrets and personal data.
6. Add tests for supplied identifiers, generated fallback, and stable formatting.
7. If queue propagation is requested, use the event-contract evolution workflow and version tests.

## Diagnostic safety

Safe fields usually include:

- exception class;
- stable operation name;
- message ID;
- trace ID;
- non-sensitive internal status.

Do not log:

- access tokens, JWTs, signing keys, API keys, or passwords;
- database or broker connection strings;
- notification content;
- complete queue payloads;
- unnecessary personal data.

Keep file names and stack traces in protected internal logs. Do not add them to shared-environment responses.

## Verified Backendbase invariants and limits

- The channel is `backendbase-app` in the reference repository.
- The processor stores the value as `extra.trace_id`.
- The HTTP boundary reads `X-Request-Id` and otherwise creates UUIDv7.
- Logger output goes to standard output only when a specific lowercase environment key exists; this is a local convention, not a portable rule.
- Queue envelopes do not currently carry trace context.
- Some existing error paths log full traces. Do not expand that behavior to sensitive payloads.
- No metrics or tracing exporter implementation exists in the repository.

## Authorization boundary

Changing code and tests does not authorize altering centralized log retention, shipping, dashboards, exporters, or production configuration.

## Verification

Run the newly added logger-factory test first. Then run the target equivalents of:

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Http
vendor/bin/phpunit tests/Shared/Http/Actions
composer phpstan
composer cs-check
```

If a message contract changes, also run its schema and producer-to-carrier mapping tests.

## Completion report

Report correlation source, validation, canonical field, fallback, affected boundaries, redactions, contract changes, tests, and external observability work not performed.

## Provenance

Verified on 2026-08-25 from:

- `config/autoload/logger.global.php`
- `config/dependencies/logger.php`
- `public/index.php`
- `src/Backendbase/Infrastructure/Adapters/Http/HttpErrorHandler.php`
- `src/Backendbase/Shared/Http/Handlers/ShutdownHandler.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php`
- `resources/platform/16-errors-observability.md`
- `resources/docs/11-error-handling-and-observability.html`
