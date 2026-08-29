# Backendbase Core

An MIT-licensed PHP 8.5 application template for Backendbase API services. It uses Slim, Domain-Driven Design (DDD), Command Query Responsibility Segregation (CQRS), Doctrine, and transactional messaging.

## API Services
```sh
composer --timeout=0 run start-apis
```

## Local Infrastructure

```sh
docker compose up -d
```

MySQL, Redis, Redis Insight, and RabbitMQ bind to <code>127.0.0.1</code> by default. Configure their local ports and credentials with the <code>BACKENDBASE_DEV_*</code> variables in <code>.env.example</code>.

RabbitMQ Management: http://127.0.0.1:15672

Start the optional AWS integration profile when you need local S3, SQS, or SNS endpoints:

```sh
docker compose --profile integration up -d
```

The profile starts Moto on `http://127.0.0.1:5000`. The default command does not start or download this service.

Use non-secret local credentials and the shared endpoint in `.env`:

```dotenv
BACKENDBASE_QUEUE_DRIVER=sqs
AWS_ACCESS_KEY_ID=backendbase
AWS_SECRET_ACCESS_KEY=backendbase
AWS_REGION=eu-central-1
AWS_ENDPOINT=http://127.0.0.1:5000
AWS_SQS_QUEUE=backendbase-queue
OBJECT_STORE_ACCESS_KEY=backendbase
OBJECT_STORE_SECRET_KEY=backendbase
OBJECT_STORE_REGION=eu-central-1
BUCKET_NAME=backendbase-local
```

Moto starts without resources and does not persist them. Create required buckets, queues, and topics in each integration test setup.

## CLI Commands

###  Usage
```sh
bin/backendbase [command] [options]
```

### Transactional outbox

Apply migrations before a service publishes integration events:

```sh
bin/doctrine migrations:migrate --no-interaction
```

Run the relay from a scheduler or worker. Repeated runs publish pending messages and retry temporary failures:

```sh
bin/backendbase outbox:relay --limit=100
```

Monitor retries and the oldest pending message:

```sh
bin/backendbase outbox:status --max-pending-age=300
```

The command returns a failure status for retried messages or messages older than the configured age.

Rejected queue messages move to the durable `<queue>.dead-letter` queue. Primary queue messages expire to that queue after seven days.

### AWS SQS and SNS

Set `BACKENDBASE_QUEUE_DRIVER=sqs` to bind `BackendbaseQueue` to Amazon SQS. Keep the default `rabbitmq` value to use RabbitMQ.

Configure `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_REGION` for local credentials. Leave both key values empty to use the standard AWS credential provider chain. Set `AWS_ENDPOINT` when using an AWS-compatible local service. Object storage uses `OBJECT_STORE_ENDPOINT` when set and otherwise uses `AWS_ENDPOINT`.

`Notify` registers Amazon SNS as the `sms` provider. Send an SMS through the existing notification stack:

```php
use Backendbase\Shared\Primitives\Notification\SmsNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;

$notification = new StackNotification()->addNotification(
    new SmsNotification('+905551112233', 'Your verification code is 123456.'),
);
$notifier->notify($notification);
```

Set `AWS_SNS_SENDER_ID` when the destination country supports sender IDs. Use the SQS and SNS variables in `.env.example` for all other service settings.

Delete completed inbox and outbox records with a scheduled retention job:

```sh
bin/backendbase integration-messages:cleanup --retention-days=30 --limit=1000
```

## API E2E Tests

### Usage

```sh
bin/bruno [collection] [environment]
```

### Example

```sh
bin/bruno example-api local
```

Each run writes a human-readable HTML report to `artifacts/bruno/{collection}/{environment}.html`.
The selected environment must point to a running API with a prepared account and its required privileges.

## Project Policies

- Read [CONTRIBUTING.md](CONTRIBUTING.md) before you propose a change.
- Report vulnerabilities as described in [SECURITY.md](SECURITY.md).
- Review release changes in [CHANGELOG.md](CHANGELOG.md).
- See [LICENSE](LICENSE) for the MIT license terms.
