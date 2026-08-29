# Transactional Outbox

The transactional outbox separates business completion from broker availability.

## Write and relay flow

1. A command handler passes authoritative database work to `IntegrationEventTransaction`.
2. The callback changes business state and returns a versioned integration event.
3. `IntegrationEventTransaction` commits business state and the outbox row together.
4. `OutboxRelayService` selects the claim duration and asks `OutboxMessageStore` to claim the oldest available row.
5. The selected queue adapter publishes the message.
6. The service tells the store to mark success or record the calculated retry state.

MySQL uses `FOR UPDATE SKIP LOCKED`. Each relay also uses a 60-second claim token.

A publish can succeed before the row is marked as published. A later relay can publish the same event again.

Consumers must therefore be idempotent. Do not treat broker delivery as exactly once.

The outbox stores event name, version, payload, occurrence time, attempts, errors, availability, publication time, and claim state.

Publication failures remain pending. `OutboxRetryPolicy` owns the 60-second claim and the 2-to-256-second retry delay. No terminal attempt limit exists.

`DoctrineOutboxMessageStore` only claims rows and applies supplied publication or failure state. It does not publish messages or calculate retry policy.

Monitor old pending and retried rows with `bin/backendbase outbox:status --max-pending-age=300`.

Basis: `resources/docs/4-messaging-and-queues.html`.
