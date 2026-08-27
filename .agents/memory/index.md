# Project Memory Index

- Memory protocol: `.agents/memory/README.md`.
- Orchestration boundary: `.agents/memory/orchestration.md`.
- Project facts: `.agents/memory/project.md`.
- Durable decisions: `.agents/memory/decisions/`.
- Scoped domain notes: `.agents/memory/domains/`.
- Active handoffs: `rg -l '^status: active$' .agents/memory/handoffs`.
- Archived handoffs: `.agents/memory/handoffs/archive/`.
- Reusable learnings: `.agents/memory/learnings/`.

Do not list each handoff here. Use `rg -l '^scope: <scope>$' .agents/memory/handoffs .agents/memory/handoffs/archive` for scoped discovery.
