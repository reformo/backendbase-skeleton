---
name: backendbase-add-bruno-api-test
description: Add an executable Bruno request or lifecycle test for an existing Backendbase-style API operation. Use for running-API evidence; do not use for PHPUnit tests, OpenAPI-only edits, or endpoint implementation.
---

# Add a Bruno API test

## Outcome

Add a safe, repeatable Bruno request that uses environment variables, asserts the important contract, and fits the collection's authentication and lifecycle order.

## Required discovery

1. Read applicable `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, architecture, container and test layout, even when no PHP edit is expected.
3. Discover the owning API from its selector, runtime route, OpenAPI root, collection root, and wrapper command. Do not assume a fixed API list.
4. Find the environment format, nearest request, complete application path, runtime response, report policy, and cleanup strategy.
5. Resolve the target environment, required credentials, fixture ownership, runtime-generated identifiers, and whether the request changes state.
6. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).

Do not create a request for an unimplemented operation. Do not run state-changing tests against an unapproved target.

## Target-project adaptation

Use the target collection format, variable names, base URL, headers, authentication flow, fixture source, and report policy. Never copy Backendbase tokens, API keys, hosts, source IDs, passwords, user data, or fixed resource identifiers.

## Workflow

1. Map method, URL, parameters, headers, body, response, and security from the public contract.
2. Add missing environment variables with safe placeholders.
3. Place authentication before protected requests and capture runtime tokens when needed.
4. Generate run-unique input for state-changing requests.
5. Capture identifiers and tokens from runtime responses or headers. Do not assume fixed IDs for later requests.
6. Choose a sequence that respects create, read or list, update, and delete dependencies.
7. Add focused assertions for status, response headers, wrapper keys, important fields, and pagination when applicable.
8. Add deterministic cleanup and report whether it completed. Make interrupted or partial runs safe to repeat.
9. Compare the request semantically with the runtime route, OpenAPI operation, middleware policy, and focused tests.
10. Check report settings for credential and body disclosure.
11. Run only against a prepared, authorized target.

## Backendbase invariants

- Requests are YAML files under the API collection.
- Common headers come from environment variables.
- Protected requests send API key and bearer credentials when both are required.
- Authentication captures `accessToken` for later requests.
- Create operations capture the target resource identifier from the runtime response or a documented response header when later requests need it.
- Versioned environments contain placeholders, not live secrets.
- Assertions check status and contract-significant fields.
- State-changing lifecycles use run-unique data and deterministic cleanup.
- Reports exclude authorization headers, API keys, and request or response bodies.

## Verification

Start the required service, prepare safe data, then run:

```sh
bin/bruno {api-slug} {environment}
```

Review the HTML report path and confirm that it contains no credentials or personal data.

## Completion report

Report the request file, sequence, runtime values captured, environment variables, assertions, target used, cleanup result, and any skipped execution.
