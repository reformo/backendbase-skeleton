---
name: backendbase-add-notification-provider
description: Add and register a provider adapter for an existing or requested notification type in a Backendbase-style project. Do not use for queue delivery, outbox processing, or a non-notification external service.
---

# Add a notification provider

## Outcome

Add one provider-independent notification model or reuse an existing one, map it to a vendor adapter, register the stable type, and prove configuration and failure behavior with doubles.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the notification port, installed provider client, nearest notifier, configuration, registration, and focused tests.
3. Trace every producer and consumer that can create the notification type.
4. Resolve the stable type name, required fields, sensitive content, provider response, credentials, timeout, and failure semantics.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not add a provider, channel, credential, or delivery route that the user did not request.

## Target-project adaptation

Use the target notification contract, provider SDK, config keys, container, model validation, logging policy, and tests. Never copy Backendbase sender IDs, tokens, phone numbers, addresses, credentials, endpoints, or message bodies.

## Workflow

1. Define or confirm the provider-independent notification data and stable type.
2. Enforce required external invariants before the provider call.
3. Implement a `NotificationProvider` adapter that accepts only its supported model.
4. Map project values to one vendor request and validate the vendor result.
5. Translate recoverable failures without exposing SDK types.
6. Add validated settings and finite network timeouts.
7. Register the adapter in `StackNotifier` for every produced type.
8. Test model validation, request mapping, provider failure, registration, and missing-provider behavior with doubles.

## Backendbase invariants

- Notification models are provider-independent.
- Models and providers use the same stable `type()` value.
- `StackNotifier` has one registered provider for every type that production code creates. A direct `Notification` uses the same routing as a grouped notification.
- A provider rejects an unsupported notification model.
- Vendor types remain in infrastructure.
- Notification bodies, recipients, tokens, and attachments are not logged.
- Automated tests never send live email, push, or SMS.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit tests/Shared/Primitives/Notification
vendor/bin/phpunit tests/Infrastructure/Adapters/Notification
vendor/bin/phpunit tests/Infrastructure/Adapters/AwsDependencyDefinitionsTest.php
composer phpstan
composer complexity
composer cs-check
```

Use the target container test when the provider is not AWS-backed.

## Completion report

Report the notification type, model invariants, adapter, container registration, sensitive logging review, tests, and blocked required checks.
