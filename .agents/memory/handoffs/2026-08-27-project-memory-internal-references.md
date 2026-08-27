---
id: 2026-08-27-project-memory-internal-references
status: complete
projectionStatus: current
mode: manual
scope: project-memory
updated: 2026-08-27
owner: Codex
confidence: verified
runId: null
taskId: null
taskVersion: null
planRevision: null
configurationHash: null
checkpointHash: null
taskStatus: null
runStatus: null
phase: null
---

# Goal

Remove or replace file references that point outside `.agents/memory`.

# Canonical Orchestration Checkpoint

Not applicable. This task is manual.

# Verified Facts

- Explicit file references now point inside `.agents/memory`.
- Each explicit internal target exists.

# Changes

- Replaced the external knowledge index with the project memory protocol.
- Replaced external project facts with internal memory records.
- Generalized the historical repository change references.
- Replaced ambiguous file names with clear internal terms or paths.

# Decisions

- Preserved the historical handoff because its result remains useful.

# Verification

- The outside-reference scan found no matches.
- The path-token scan found no external target.
- The changed files contain no trailing whitespace.

# Next Action

None.

# Blockers

None.

# Assumptions

- The request applies to references stored in `.agents/memory`.

# Evidence

- `.agents/memory/index.md`
- `.agents/memory/README.md`
- `.agents/memory/project.md`
- `.agents/memory/handoffs/2026-08-27-agent-rules-language-rules.md`
