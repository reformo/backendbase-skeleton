# Transactional Outbox

The transactional outbox separates business completion from broker availability.

## Write and relay flow

1. A command handler creates a versioned integration event.
2. `IntegrationEventTransaction` commits business state and the outbox row together.
3. The relay claims the oldest available row.
4. The selected queue adapter publishes the message.
5. The relay marks the row as published.

MySQL uses `FOR UPDATE SKIP LOCKED`. Each relay also uses a 60-second claim token.

A publish can succeed before the row is marked as published. A later relay can publish the same event again.

Consumers must therefore be idempotent. Do not treat broker delivery as exactly once.

The outbox stores event name, version, payload, occurrence time, attempts, errors, availability, publication time, and claim state.

Publication failures remain pending. Retry delay grows to 256 seconds, but no terminal attempt limit exists.

Monitor old pending and retried rows with `bin/backendbase outbox:status --max-pending-age=300`.

Basis: `resources/docs/4-messaging-and-queues.html`.
