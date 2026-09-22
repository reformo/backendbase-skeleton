---
name: backendbase-add-immutable-release-workflow
description: Add a Backendbase-inspired immutable release artifact, guarded deployment, recovery record, and application-only rollback workflow to a PHP service. Do not use to execute a live deployment or migration without explicit authority.
---

# Add an immutable release workflow

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover Composer scripts, generated contracts, migrations, health endpoints, server layout, process manager, CI, and deployment tests.
2. Define artifact contents, manifest fields, checksum, migration target, schema compatibility policy, hooks, and activation path.
3. Add build, guarded activation, state recording, and restricted rollback code.
4. Test success, migration failure, activation failure, unsafe archives, and rollback restrictions in an isolated temporary directory.
5. Document server-specific backup and activation hook contracts.
6. Do not run the generated workflow against a server without explicit release authority.

## Invariants

- Bind artifacts to an exact reviewed revision and dependency lock.
- Keep environment files and live secrets out of artifacts.
- Verify checksums and archive paths before extraction.
- Record migration plans and recovery data before activation.
- Roll back only to an approved recorded release and do not silently reverse schema.

## Completion report

Report generated workflow files, manifest policy, hooks, recovery behavior, tests, unexecuted deployment steps, and required operator decisions.
