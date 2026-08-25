# Queue Runtime and Operations

Set `BACKENDBASE_QUEUE_DRIVER` to `rabbitmq` or `sqs`. RabbitMQ is the default and local reference.

## Driver behavior

| Outcome | RabbitMQ | SQS |
| --- | --- | --- |
| Acknowledge | AMQP acknowledgment | Delete by receipt handle |
| Retry | Requeue immediately | Keep until visibility timeout |
| Reject | Dead-letter without requeue | Keep until redrive policy acts |

RabbitMQ declares a dead-letter queue. SQS requires an infrastructure redrive policy. The application has no automatic replay command.

The outbox uses the configured default queue or route. It does not select a route per row.

## Processes

```sh
bin/backendbase outbox:relay --limit=100
bin/backendbase outbox:status --max-pending-age=300
bin/backendbase queue:consume backendbase-queue
bin/backendbase queue:notify-consumer backendbase-queue-email
bin/backendbase integration-messages:cleanup --retention-days=30 --limit=1000
```

Supervise long-running consumers. Schedule finite relay, status, and cleanup commands. Prevent overlapping relay and cleanup runs.

Basis: `resources/docs/4-messaging-and-queues.html`.
