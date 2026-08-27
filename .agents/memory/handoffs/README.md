# Handoffs

Use one file per manual task or one derived file per logical orchestration run. Use `.agents/memory/templates/handoff.md`.

Name each file `YYYY-MM-DD-<scope>-<short-purpose>.md`. Find active files with `rg -l '^status: active$' .agents/memory/handoffs`.

Do not create one project-memory handoff per orchestration attempt. Store attempts and accepted handovers only in the canonical orchestration store.
