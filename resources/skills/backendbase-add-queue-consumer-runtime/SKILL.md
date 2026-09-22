---
name: backendbase-add-queue-consumer-runtime
description: Expose an existing Backendbase queue processor as a console worker with queue selection, container registration, transport callback wiring, and lifecycle guidance. Do not use to create the processor or broker adapter itself.
---

# Add a queue consumer runtime

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover the console bootstrap, queue port, processor, driver settings, command registration, and process tests.
2. Define the queue name source, poll or wait behavior, and shutdown model.
3. Wire one command to one processor callback.
4. Register the command through the existing container.
5. Test default and explicit queue selection and callback shape.
6. Document worker restart requirements and the required supervisor class without installing it.

## Invariants

- The callback must return the queue outcome expected by every supported transport.
- Long-running consumers require a supervisor, not cron.
- Do not hard-code Backendbase queue names in another project.
- Restart workers after release or effective configuration changes.
- Do not start a real worker or modify process-manager state without explicit authorization.

## Completion report

Report command name, queue source, processor, transport parameters, registration, runtime class, tests, and unconfigured supervisor work.
