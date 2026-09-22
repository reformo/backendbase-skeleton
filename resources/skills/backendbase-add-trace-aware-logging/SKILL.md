---
name: backendbase-add-trace-aware-logging
description: Add or extend request and message correlation fields in Backendbase-style Monolog configuration while preserving public contracts and diagnostic safety. Do not use for metrics, tracing exporters, or broad observability platforms.
---

# Add trace-aware logging

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and inspect the logger factory, formatters, HTTP bootstrap, queue envelope, and error handlers.
2. Identify the existing correlation source and one canonical log field.
3. Decide whether the change affects only logs or also a public HTTP or message contract.
4. Add one processor or boundary mapping and focused tests.
5. Review every logged context field for credentials, tokens, message content, and personal data.

## Invariants

- Reuse one correlation identifier through one operation.
- Generate a new identifier only at a true boundary when none exists.
- Keep stack traces internal and public failure bodies stable.
- Treat queue trace propagation as a versioned message-contract change.
- Do not copy Backendbase channel names, headers, or filesystem paths without target discovery.

## Completion report

Report the correlation source, canonical field, affected boundaries, redacted data, contract impact, and verification results.
