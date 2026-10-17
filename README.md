# Backendbase Core

An MIT-licensed PHP 8.5 application template for Backendbase API services. It uses Slim, Domain-Driven Design (DDD), Command Query Responsibility Segregation (CQRS), Doctrine, and transactional messaging.

## API Services
```sh
composer --timeout=0 run start-apis
```

## Project-copy Configuration

[`reformo-orchestration.ini`](reformo-orchestration.ini) declares the project-copy settings:

- `base-name` is `backendbase`.
- `omit-files` excludes the orchestration file and three listed example/account migration files from the copy.
- `preserve-manifest` preserves the target project's `composer.json` sections `require` and `require-dev`.
- `post-install-run` lists these commands in order: `cp .env.example .env`, `composer update`, `mkdir -p ./artifacts`, and `bin/dev/housekeeping.sh`.

The copy workflow replaces the target `.env` with `.env.example` before maintenance. Review the target configuration before running it.
Housekeeping requires `BACKENDBASE_ENV=dev` before it clears caches, fixes source style, generates OpenAPI, updates dependencies, and refreshes supply-chain evidence. See the [testing guide](resources/docs/10-testing-and-quality.html#automation).

Run isolated Redis Sentinel checks with `bash bin/tests/redis-failover.sh`. The runner creates and removes its own primary, replica, and three Sentinels. See [Redis failover tests](resources/platform/operations/redis-failover-tests.md).

## Local Infrastructure

```sh
docker compose up -d
```

MySQL, Redis, Redis Insight, RabbitMQ, MiniStack, Nginx, and StackPort bind to <code>127.0.0.1</code> by default. Configure their local ports and credentials with the <code>BACKENDBASE_DEV_*</code> variables in <code>.env.example</code>.

MySQL starts with `utf8mb4` and `utf8mb4_tr_0900_ai_ci` server defaults. Databases created during fresh initialization inherit these defaults. Existing database and table collations require a reviewed conversion. See [database collation procedures](resources/platform/operations/database-collation.md).

Redis uses the official Redis 8.10.2 image with JSON and Search support on AMD64 and ARM64 systems. Redis Insight runs separately at `http://127.0.0.1:8001` and connects to `redis:6379` inside Docker. Both images use pinned digests. Redis keeps its existing data path at `var/docker-data/redis`.

RabbitMQ Management: http://127.0.0.1:15672

StackPort UI: http://127.0.0.1:8082

The default stack starts MiniStack at `http://127.0.0.1:4566` for S3, SQS, SNS, SES, and CloudFront APIs. It uses the free image pinned to version 1.5.14. CloudFront distributions do not deliver content in MiniStack. Use Docker Compose 2.30 or later for the startup hook.

StackPort 0.4.3 connects to MiniStack inside Docker and shows its local AWS resources. Change `BACKENDBASE_DEV_STACKPORT_PORT` to use another host port.

Start the local message viewer with the project PHP runtime, Composer dependencies, and Docker available on the host.
Set `BACKENDBASE_ENV=dev` in the process environment or `.env`. Commands under `bin/dev` reject missing, empty, or other environment values.
Process values take precedence over `.env`. Composer checks the environment before starting the viewer. The viewer also checks every request and returns HTTP 403 outside `dev`.

```sh
composer message-viewer
```

Open [Message viewer](http://127.0.0.1:8083). Select **SMS** or **Emails**. Select an email to expand its headers and HTML preview.
Plain text emails also have previews. The page refreshes every five seconds and supports filtering.
The viewer reads SMS logs from `backendbase-ministack` and captured SES emails from the local MiniStack inspection endpoint.
It uses `BACKENDBASE_DEV_AWS_PORT` from `.env`, with port 4566 as the default. Reads have a five-second timeout and an 8 MiB limit.
Each view shows at most 200 messages, newest first. SMS capture reads the latest 2,000 log lines.
MiniStack logs up to 80 characters per SMS. The viewer shows the first line only. Container recreation or log rotation can remove older messages.
Some raw emails have no recipient in MiniStack's stored record. The viewer marks that field as unavailable.
Email previews block scripts, forms, links, and remote images. Attachments are not available through the capture endpoint.
The viewer binds to loopback, sends no notifications, and writes no message files. It does not capture Twilio, Netgsm, or SMTP delivery.
Stop the viewer with Ctrl+C. Use `php -S 127.0.0.1:8084 bin/dev/message-viewer.php` when port 8083 is unavailable.

Nginx serves objects from the configured MiniStack bucket at `http://127.0.0.1:8081/`. It allows GET and HEAD requests and caches successful responses for 60 seconds. This URL is available from the local host.

Use the local settings in `.env.example` when you create `.env`. It includes distinct User and Admin signing keys and a contact verification proof key. These public examples are valid only for local development. Replace them with independent secrets before deployment. Optional provider credentials, Redis authentication, and previous keys remain commented until configured. The AWS keys are test values:

```dotenv
BACKENDBASE_QUEUE_DRIVER=sqs
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_REGION=eu-central-1
AWS_ENDPOINT=http://127.0.0.1:4566
AWS_SQS_QUEUE=backendbase-queue
AWS_SQS_QUEUE_URL=http://127.0.0.1:4566/000000000000/backendbase-queue
OBJECT_STORE_ACCESS_KEY=test
OBJECT_STORE_SECRET_KEY=test
OBJECT_STORE_REGION=eu-central-1
OBJECT_STORE_ENDPOINT=http://127.0.0.1:4566
BUCKET_NAME=backendbase-v3
CDN_BASE_URL=http://127.0.0.1:8081/
```

MiniStack creates the configured S3 bucket and SQS queue at startup. It saves state and S3 objects in the `ministack_state` and `ministack_s3` Docker volumes. Create SNS topics and SES identities when a local workflow needs them. Set `CDN_BASE_URL` to the Nginx URL with a trailing slash. Change `BACKENDBASE_DEV_CDN_PORT` and `CDN_BASE_URL` together when the default port is unavailable.

## CLI Commands

###  Usage
```sh
bin/backendbase [command] [options]
```

### Transactional outbox

Return integration events from `IntegrationEventTransaction::execute()`. Before commit, the event manager runs local subscribers for both flag values. `DELIVER_VIA_QUEUE = true` also appends an outbox row; `false` keeps delivery local. Business writes, local subscriber writes, and the outbox insert share one database transaction. A failure rolls back those writes.

Apply migrations before a service publishes integration events:

```sh
bin/doctrine migrations:migrate --no-interaction
```

Run the continuous relay and queue consumer as separate supervised processes. The relay checks for new outbox rows about every 250 ms when a batch is not full:

```sh
bin/backendbase outbox:relay --continuous --limit=100
bin/backendbase queue:consume backendbase-queue
```

Use `bin/backendbase outbox:relay --limit=100` for a single batch. The continuous relay retries temporary publication failures according to the outbox retry schedule.

Monitor retries and the oldest pending message:

```sh
bin/backendbase outbox:status --max-pending-age=300
```

The command returns a failure status for retried messages or messages older than the configured age.

Rejected queue messages move to the durable `<queue>.dead-letter` queue. Primary queue messages expire to that queue after seven days.

### AWS SQS and notifications

Set `BACKENDBASE_QUEUE_DRIVER=sqs` to bind `BackendbaseQueue` to Amazon SQS. Keep the default `rabbitmq` value to use RabbitMQ.

Configure `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_REGION` for local credentials. Leave both key values empty to use the standard AWS credential provider chain. Set `AWS_ENDPOINT` when using an AWS-compatible local service. Object storage uses `OBJECT_STORE_ENDPOINT` when set and otherwise uses `AWS_ENDPOINT`.

`Notify` routes SMS to Amazon SNS and email to Amazon SES by default. Set `BACKENDBASE_SMS_DRIVER=twilio` or `netgsm` to select an SMS provider. Send one SMS directly:

```php
use Backendbase\Shared\Primitives\Notification\SmsNotification;
$notifier->notify(new SmsNotification('+905551112233', 'Your verification code is 123456.'));
```

Set `BACKENDBASE_EMAIL_DRIVER=smtp` and the `SMTP_*` values to use SMTP. SES uses the existing AWS region, credentials, and endpoint. Set `FIREBASE_PROJECT_ID` to register push delivery. Set `FIREBASE_CREDENTIALS_PATH` for a service-account file, or use Google application credentials. A grouped `StackNotification` returns each delivery result in order. A partial failure reports completed deliveries through `NotificationBatchFailed`.

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
The selected environment must point to a prepared API with the configured API key.

## Project Policies

- Read [CONTRIBUTING.md](CONTRIBUTING.md) before you propose a change.
- Report vulnerabilities as described in [SECURITY.md](SECURITY.md).
- Review release changes in [CHANGELOG.md](CHANGELOG.md).
- See [LICENSE](LICENSE) for the MIT license terms.
