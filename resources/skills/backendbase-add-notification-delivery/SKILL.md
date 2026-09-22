---
name: backendbase-add-notification-delivery
description: Add or change queued notification delivery with message validation, external-effect idempotency, and failure classification. Do not use only to add a provider or a general queue consumer.
---

# Add notification delivery

## Outcome

Deliver one notification message at most once through the external-effect boundary, with explicit acknowledge, retry, reject, and unknown-outcome behavior.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the notification contract, queue processor, external-effect persistence, registration, and focused failure-path tests.
3. Trace the producer schema, topic, message identifier, registered provider type, inbox transaction, and failure policy.
4. Resolve malformed, transient, permanent, in-progress, duplicate, and unknown-outcome behavior.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not create a new queue, provider, topic, schema, or external mutation outside the requested delivery path.

## Target-project adaptation

Use the target queue envelope, notification model, provider registry, inbox storage, transaction boundary, retry limits, dead-letter policy, and logging fields. Never copy Backendbase topics, message IDs, payloads, recipients, credentials, or fixture data.

## Workflow

1. Define and validate topic, message ID, body schema, and notification type.
2. Map the body to a fully valid provider-independent notification.
3. Invoke the provider only inside the external-effect inbox operation.
4. Mark success and acknowledge duplicates without another provider call.
5. Retry work already in progress according to queue timing.
6. Treat an unknown external outcome as permanent until an operator establishes the result.
7. Classify malformed input as reject or permanent failure and transport failures as bounded transient failures.
8. Test every outcome without a live provider or broker.

## Backendbase invariants

- Metadata is validated before inbox or provider work.
- `ExternalEffectInbox::processOnce()` encloses the external call.
- A duplicate acknowledges without sending again.
- In-progress work retries.
- Unknown provider outcome is not automatically retried.
- Success clears recorded failure state.
- Producer, processor, notification model, and provider share one type contract.
- Logs exclude notification content and personal data.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit <target-notification-processor-test>
vendor/bin/phpunit tests/Infrastructure/Adapters/Persistence/Doctrine/DoctrineExternalEffectInboxTest.php
composer phpstan
composer complexity
composer cs-check
```

## Completion report

Report the message schema, notification type, inbox boundary, outcome table, provider compatibility, tests, and blocked required checks.
