# Message Consumers and Inbox

Integration consumers use an inbox transaction. External-effect consumers use a lease because a provider call cannot join a database transaction.

## Integration work

- The inbox key is `(consumer_name, message_id)`.
- A processed duplicate returns acknowledge without repeated work.
- Success commits consumer database work and `processed_at` together.
- Permanent contract failures reject the message.
- Transient failures retry through `QueueMessageFailureService`.
- The application service makes delivery failures terminal after five attempts.
- `DoctrineQueueMessageFailureStore` only records, marks, and clears supplied failure state.
- `QueueMessageFailureService` and the database inbox read `Shared/Time/Clock` for failure and completion timestamps used by retention.

## External effects

- `ExternalEffectInbox` uses a 300-second claim.
- A processed effect acknowledges.
- An active claim retries later.
- A failed provider call can have an unknown outcome.
- Do not retry an unknown outcome automatically because it can duplicate the effect.
- Resolve unknown outcomes through provider records and the message ID.

`DoctrineExternalEffectInbox` reads the UTC clock and delegates claim persistence to `Doctrine/Inbox/ExternalEffectClaims`. A claim equal to the current instant remains active. An expired incomplete claim has an unknown outcome and must not repeat the provider call.

Cleanup reads the same clock contract. It deletes terminal records strictly older than the retention cutoff and preserves records exactly at that cutoff.

The database uses `integration_event_inbox` and `integration_event_delivery_failure` for deduplication, leases, attempts, and terminal state.

Basis: `resources/docs/4-messaging-and-queues.html`.
