# Transactional Outbox

The transactional outbox separates business completion from broker availability.

## Write and relay flow

1. A command handler passes authoritative database work to `IntegrationEventTransaction`.
2. The callback changes business state and returns a versioned integration event.
3. Before commit, `IntegrationEventTransaction` passes the event to `ContainerAwareEventManager`.
4. The event manager requires an active transaction and runs local subscribers synchronously for both flag values.
5. When `DELIVER_VIA_QUEUE` is `true`, the event manager appends one row through `IntegrationEventOutbox`, even without local subscribers. `DoctrineIntegrationEventOutbox` owns the insert.
6. The shared transaction commits business writes, subscriber writes, and the optional outbox row together. Any failure rolls back those writes.
7. `OutboxRelayService` selects the claim duration and asks `OutboxMessageStore` to claim the oldest available committed row.
8. The selected queue adapter publishes the message.
9. The service tells the store to mark success or record the calculated retry state.

The transaction wrapper, local subscriber repositories, and outbox writer must share one database connection. Keep external effects outside local subscribers. Queue consumption uses a separate dispatch method that does not republish the received event.

MySQL uses `FOR UPDATE SKIP LOCKED`. Each relay also uses a 60-second claim token.

A publish can succeed before the row is marked as published. A later relay can publish the same event again.

Consumers must therefore be idempotent. Do not treat broker delivery as exactly once.

The outbox stores event name, version, payload, occurrence time, attempts, errors, availability, publication time, and claim state.

On a new database, `Version20260823000000` runs first and creates the outbox, inbox, and delivery-failure tables with all required indexes.

Publication failures remain pending. `OutboxRetryPolicy` owns the 60-second claim and the 2-to-256-second retry delay. No terminal attempt limit exists.

`DoctrineOutboxMessageStore` only claims rows and applies supplied publication or failure state. It does not publish messages or calculate retry policy.

Monitor old pending and retried rows with `bin/backendbase outbox:status --max-pending-age=300`.

Basis: `resources/docs/4-messaging-and-queues.html`.
