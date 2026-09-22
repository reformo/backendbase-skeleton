---
name: backendbase-add-object-storage-capability
description: Add or extend object-storage download, upload, or signed-browser-upload behavior behind a Backendbase-style port. Do not use for a general vendor API, HTTP endpoint, or unrelated file processing.
---

# Add an object-storage capability

## Outcome

Add one storage operation with a project-owned contract, private and bounded access rules, validated configuration, vendor isolation, and deterministic adapter tests.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the storage port, installed client, nearest operation, configuration, readiness contract, and focused tests.
3. Resolve the caller, object key rules, bucket or container, provider, endpoint, credential strategy, content type, access level, expiration, and retry policy.
4. Confirm whether a CDN or direct object-store URL owns downloads.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not add a bucket, public access policy, provider, endpoint, or upload path without user intent.

## Target-project adaptation

Use the target port, provider SDK, storage terminology, key policy, container wiring, credential chain, endpoint, CDN, error model, and tests. Never copy Backendbase bucket names, regions, credentials, URLs, hosts, or fixture files.

## Workflow

1. Define the storage action and project result without SDK types.
2. Extend the narrow port only when the requested action belongs there.
3. Validate keys, paths, content types, sizes, expiration, and access before the provider call.
4. Implement provider mapping in infrastructure.
5. Configure finite timeouts and bounded retry for safe operations.
6. Support credential-chain or explicit credentials according to target deployment policy.
7. Register provider and port in the container and include readiness when required.
8. Test URLs, request policy, upload success, malformed results, timeout, retry exhaustion, and invalid input with SDK doubles.

## Backendbase invariants

- Callers depend on `BucketService`, not AWS types.
- Signed browser uploads are private and limited to the intended key policy.
- Expiration is explicit, positive, and bounded.
- Multipart retries are finite and safe.
- The supplied content type is enforced when it is part of the contract.
- Download content type is not silently forced to an unrelated value.
- Credentials and signed form values are not logged.
- Automated tests use SDK handlers or doubles, never live storage.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/S3BucketTest.php
vendor/bin/phpunit tests/Infrastructure/Adapters/AwsDependencyDefinitionsTest.php
composer phpstan
composer complexity
composer cs-check
```

Use the corresponding target test paths when the provider is not S3.

## Completion report

Report the action, key and access policy, provider mapping, timeout and retry policy, container changes, tests, and blocked required checks.
