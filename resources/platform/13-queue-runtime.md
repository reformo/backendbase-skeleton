# Queue Runtime and Operations

Set `BACKENDBASE_QUEUE_DRIVER` to `rabbitmq` or `sqs`. RabbitMQ is the default and local reference.

Queue clients depend on separate `MessagePublisher` and `MessageConsumer` ports. The ports exchange typed `Message`, `MessageSubscription`, and `MessagePublicationResult` objects. Vendor results stay inside Infrastructure adapters.

The composition root configures the RabbitMQ readiness connection factory. The readiness check requests a temporary connection and closes it after the probe.

## Driver behavior

| Outcome | RabbitMQ | SQS |
| --- | --- | --- |
| Acknowledge | AMQP acknowledgment | Delete by receipt handle |
| Retry | Requeue immediately | Keep until visibility timeout |
| Reject | Dead-letter without requeue | Keep until redrive policy acts |

RabbitMQ declares a dead-letter queue. SQS requires an infrastructure redrive policy. The application has no automatic replay command.

When an SQS handler throws, the transport logs the exception type, queue name, message identifier, location, and trace. It does not log the message body or receipt handle. The adapter does not acknowledge the message, so the visibility timeout still controls retry.

The outbox uses the configured default queue or route. It does not select a route per row.

## Processes

```sh
bin/backendbase outbox:relay --continuous --limit=100
bin/backendbase outbox:status --max-pending-age=300
bin/backendbase queue:consume backendbase-queue
bin/backendbase integration-messages:cleanup --retention-days=30 --limit=1000
```

No notification queue consumer is registered. Add one only with a complete message schema and a compatible provider.

Run the continuous relay and consumer as separate supervised processes. The relay drains full batches without waiting. After a partial or empty batch, it checks again in about 250 ms. Keep status and cleanup on schedules. Use `outbox:relay --limit=100` for one finite batch. Do not schedule the finite relay when the continuous relay is running. Prevent overlapping relay and cleanup runs.

## Greeting example

`POST /examples/hello` on the Example API accepts `{"fullname":"Ada Lovelace"}`. An API key, bearer token, and `example.add` privilege are required. A successful request returns `202` after it writes `Example_GreetingRequested` version `1.0` to the outbox.

Keep `bin/backendbase outbox:relay --continuous --limit=100` and `bin/backendbase queue:consume backendbase-queue` running to publish the message and print `hello Ada Lovelace` in the worker output. The consumer uses the existing inbox key to skip a delivered duplicate. Output can repeat if the worker writes it but fails before the inbox transaction commits.

Basis: `resources/docs/4-messaging-and-queues.html`.
