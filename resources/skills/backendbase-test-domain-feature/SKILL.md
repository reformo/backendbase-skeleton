---
name: backendbase-test-domain-feature
description: Add focused Backendbase tests for domain values, aggregates, CQRS contracts and handlers, persistence adapters, or complete in-process lifecycles. Use when behavior needs executable evidence; do not use to implement missing production behavior, replace database tests with memory tests, or make live external calls.
---

# Test a Backendbase Domain Feature

## Outcome

Add the smallest deterministic test layer that proves the requested behavior and its important failure paths.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the behavior under test, nearest test at the same boundary, test configuration, fixture isolation, and test ownership.
3. Trace the production contract, current failure behavior, ports, adapters, and event or transaction boundaries.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Select the test mode: domain, CQRS, repository, lifecycle, or composition and registration. Use multiple modes only when each proves a different risk.

## Target-project adaptation

Preserve the target project's test ownership, naming, fixtures, database setup, doubles, and assertion style. Do not copy Example data, IDs, timestamps, table names, or expected payloads.

## Workflow

1. State the observable behavior and failure that the test must prove.
2. Start with the narrowest changed or failing test file.
3. For domain mode, test valid, invalid, boundary, and state-transition behavior without adapters.
4. For CQRS mode, test contract serialization and handler use of ports, domain behavior, transactions, and events.
5. For repository mode, define one behavioral contract suite and run it against every adapter for the port. Keep Doctrine-specific mapping and rollback tests separate.
6. For lifecycle mode, bind memory adapters and deterministic doubles, then use real command and query buses.
7. For composition and registration mode, build the real test container or runtime registry and prove the new handler, port, subscriber, module, or command is reachable.
8. Expand to owning directories, architecture checks, static analysis, style, and the full suite in proportion to risk.

## Backendbase invariants

- Prefer the target project's established test ownership. In an unmodified Backendbase project, keep new movable context tests under the context `Tests` root and platform, shared, infrastructure, API, and architecture tests under root `tests`.
- Keep movable domain, application, contract, repository, and provider tests under the context `Tests` root.
- Do not use handwritten test DDL for mapped ORM records; use `SchemaTool` with production metadata.
- Use production Doctrine metadata and an isolated schema for Doctrine repository tests.
- Do not claim database constraint enforcement, SQL, transaction, or MySQL behavior from memory tests.
- Do not make live network or provider calls.
- Assert failure, invalid input, rollback, mapping, and not-found paths when they are part of the change.
- Test event payloads and ordering when they are contracts.
- Do not treat a direct handler test or a passing architecture test as proof of runtime reachability. Exercise the actual container, bus, provider, or registry when registration changed.
- Do not change production behavior merely to make a weak test pass.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run the changed test file or its owning directory according to the affected behavior. Add database or lifecycle tests for affected contracts. Run PHPStan level 8, the configured complexity check, and PHPCS. Run the full suite for broad changes.

## Completion report

Report the behavior proven at each test layer, commands and results, remaining untested risks, and blocked required checks and their causes.
