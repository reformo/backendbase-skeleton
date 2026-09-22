# Backendbase queue consumer runtime pattern

This pattern exposes an existing message processor through a console worker. It does not create the processor, broker adapter, queue, or supervisor.

## Target discovery

Resolve only unknown facts needed by the affected behavior. Reuse verified facts while their sources remain unchanged.

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, console bootstrap, queue port, transport configuration, processor signature, command registration, tests, and deployment process manager.
3. Identify the queue name source and supported transport drivers.
4. Determine whether `consume()` is continuous, batch-based, push-based, or controlled by the command.
5. Record current worker shutdown, signal, and restart conventions.

Do not copy `bin/backendbase`, `backendbase-queue`, service names, host paths, or polling values without target discovery.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Executable | `bin/backendbase` | Reuse the target console entry point. |
| Worker command | `ContainerAwareQueueConsumer` | Add one command per processor role when useful. |
| Transport port | `MessageConsumer` | Use the target consumption-only abstraction. |
| Processor | callable returning queue outcome | Inject one existing processor. |
| Queue selection | argument, setting, or deployment value | Use one documented source of truth. |
| Process ownership | external supervisor | Document, but do not install without authority. |

## Small worker example

Names and values are illustrative. Select defaults from target configuration, not source code copies.

```php
final class ConsumeImportMessages extends Command
{
    public function __construct(
        private readonly MessageConsumer $consumer,
        private readonly ImportMessageProcessor $processor,
        private readonly string $defaultQueue,
    ) {
        parent::__construct('queue:consume-imports');
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = $input->getArgument('name') ?? $this->defaultQueue;
        if (! is_string($queueName) || $queueName === '') {
            $output->writeln('<error>The queue name is required.</error>');

            return self::INVALID;
        }

        $this->consumer->consume(
            new MessageSubscription($queueName),
            fn (Message $message): QueueMessageHandlingOutcome => $this->processor->process($message),
        );

        return self::SUCCESS;
    }
}
```

If the transport owns continuous polling, the command may return only when the worker stops. Ensure the container and connections remain valid for that lifetime.

## Runtime workflow

1. Confirm the processor already returns the common outcome type.
2. Select the queue through configuration with an optional validated command override.
3. Pass only transport parameters understood by all selected drivers.
4. Wire the processor as the consume callback.
5. Register the command in the target container.
6. Test default queue, explicit queue, callback type, and successful command exit.
7. Document process owner, restart condition, concurrency, memory limits, and graceful stop behavior.

## Transport differences to preserve

| Outcome | RabbitMQ reference | SQS reference |
| --- | --- | --- |
| Acknowledge | AMQP acknowledgment | delete by receipt handle |
| Retry | negative acknowledgment with immediate requeue | keep until visibility timeout |
| Reject | reject without requeue to dead-letter route | keep until redrive policy acts |

The command must return the same processor outcome to either adapter. Broker-specific acknowledgment code belongs in the adapter.

## Finite versus long-running work

- Queue consumers are long-running and require systemd, Supervisor, Kubernetes, or another service manager.
- Outbox relay, status, and cleanup are finite and belong in a scheduler or managed finite loop.
- Cron alone does not reliably restart a failed long-running consumer.
- Worker processes must restart after releases or effective configuration changes.

The Backendbase repository does not contain a supervisor or scheduler definition. Generate one only after the user selects the target platform and authorizes that scope.

## Invariants and risks

- Validate a user-supplied queue name at the CLI boundary.
- Do not hard-code a queue name that belongs to deployment configuration.
- The consume callback must return the exact target outcome type.
- Do not acknowledge inside the command.
- Do not put application mapping in the command.
- Long-running PHP containers can retain stale settings and service state.
- A real worker can consume and delete messages. Treat starting it as an external mutation.

## Authorization boundary

Do not start a worker, install a service unit, alter concurrency, stop a live process, or create a queue without explicit authorization.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit tests/Infrastructure/UseCase/Console/Queue/QueueConsumerCommandsTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessorTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/Queue/SqsQueueTest.php
bin/backendbase list queue
composer phpstan
composer complexity
composer cs-check
```

Run the target project's new runtime tests first. The listed Backendbase paths show verified command, processor, and adapter test roles. Adapt the executable and paths to the target. Do not run the consumer against a shared queue as a verification step.

## Completion report

Report command name, processor, queue source, transport parameters, registration, long-running owner, restart conditions, tests, and worker or supervisor actions not performed.

## Provenance

Verified on 2026-08-29 from:

- `src/Backendbase/Shared/Integrations/MessageConsumer.php`
- `src/Backendbase/Shared/Integrations/Messaging/Message.php`
- `src/Backendbase/Shared/Integrations/Messaging/MessageSubscription.php`
- `src/Backendbase/Infrastructure/UseCase/Console/Queue/ContainerAwareQueueConsumer.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/RabbitMQ.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/SqsQueue.php`
- `config/commands.php`
- `tests/Infrastructure/UseCase/Console/Queue/QueueConsumerCommandsTest.php`
- `resources/platform/13-queue-runtime.md`
- `resources/platform/18-deployment.md`
- `resources/docs/4-messaging-and-queues.html`
