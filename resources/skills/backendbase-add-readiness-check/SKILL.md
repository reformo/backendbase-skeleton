---
name: backendbase-add-readiness-check
description: Add a safe, bounded dependency readiness check to a Backendbase-derived service, including deferred container registration and HTTP behavior tests. Do not use for business health metrics or liveness-only endpoints.
---

# Add a Backendbase readiness check

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover the target namespace, health contracts, container, routes, and tests.
2. Identify one cheap dependency operation that proves readiness without changing remote state.
3. Define a finite timeout and a public-safe check name.
4. Implement the check behind the project-owned readiness contract.
5. Register it lazily with the existing readiness collection.
6. Test success, invalid response, exception hiding, and HTTP 503 behavior.

## Invariants

- Liveness must not call dependencies.
- Readiness failures must not expose endpoints, credentials, exception text, or provider payloads.
- Network checks need finite connection and request timeouts.
- A check must throw on failure and let the readiness aggregator produce safe status data.
- Do not create or mutate an external resource while checking it.

## Completion report

Report the dependency, probe, timeout, safe public name, registration path, tests run, and required live checks that could not run.
