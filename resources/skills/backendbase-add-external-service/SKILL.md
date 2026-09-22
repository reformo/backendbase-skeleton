---
name: backendbase-add-external-service
description: Add a project-owned port and infrastructure adapter for a vendor API in a Backendbase-style project. Use for a general external capability; do not use for object storage or notification-specific work.
---

# Add an external service

## Outcome

Add one narrow external capability whose vendor SDK remains behind a project-owned interface, with validated configuration, bounded failures, container wiring, and deterministic tests.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the owning port, installed client dependency, nearest external adapter, configuration, registration, and boundary tests.
3. Identify the owning bounded context and exact external action.
4. Verify the current official vendor contract and SDK behavior when they can change.
5. Resolve timeouts, idempotency, retryable failures, rate limits, credentials, response validation, and logging constraints.
6. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not add a provider, credential, network call, or extra operation that the user did not request.

## Target-project adaptation

Use the target context, namespace, port location, SDK, container, config keys, exception taxonomy, logging, and test doubles. Never copy Backendbase vendor settings, endpoints, secrets, account values, hosts, or fixtures.

## Workflow

1. Express the required external action in project terms.
2. Put a context-specific port in the owning context; use Shared only for stable cross-context meaning.
3. Define project request and result values without vendor types.
4. Implement one infrastructure adapter that maps requests, responses, and recoverable failures.
5. Validate settings before the first request and configure finite connect and request timeouts.
6. Retry only transient, idempotent work with a bound and backoff.
7. Register the client, adapter, and port in the container.
8. Test mapping, malformed responses, timeout, permanent failure, retry exhaustion, and secret-safe logs with doubles.

## Backendbase invariants

- Domain and application code depend on project-owned interfaces.
- Vendor request, response, and exception types remain in infrastructure.
- External responses are validated before trusted use.
- Recoverable vendor failures become stable project exceptions with the original cause preserved.
- Retries are bounded and used only when the operation is safe to repeat.
- Credentials, tokens, personal data, and sensitive payloads are never logged.
- Automated tests do not call live providers.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/{AdapterTest}.php
# Use the owning directory instead when the change needs broader coverage.
vendor/bin/phpunit tests/Infrastructure/Adapters
composer phpstan
composer complexity
composer cs-check
```

Run any provider sandbox check only with explicit authorization and safe credentials.

## Completion report

Report the owning context, port, adapter, configuration, failure policy, tests, external calls not run, and blocked required checks.
