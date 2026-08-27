---
id: 2026-08-27-agent-rules-language-rules
status: complete
projectionStatus: current
mode: manual
scope: agent-rules
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

Add the requested language and Markdown rules to the repository agent instructions.

# Canonical Orchestration Checkpoint

Not applicable. This task is manual.

# Verified Facts

- The repository agent instructions contain the three requested rules in the `Language` section.

# Changes

- Added the requested language construction rule.
- Added the requested document-editing rule.
- Added the requested Markdown reader-context rule.

# Decisions

- Added the rules to the existing `Language` section because they control agent writing behavior.

# Verification

- The repository change passed the Git whitespace-error check.

# Next Action

None.

# Blockers

None.

# Assumptions

None.

# Evidence

- The verified rule summary and check result are recorded in this handoff.
