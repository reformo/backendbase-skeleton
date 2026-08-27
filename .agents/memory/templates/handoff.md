---
id: <unique-task-or-run-id>
status: active | blocked | complete
projectionStatus: current | stale | superseded | unapproved
mode: manual | orchestrated
scope: <domain-or-project-area>
updated: YYYY-MM-DD
owner: <agent-or-human-identifier>
confidence: verified | mixed | assumption
runId: <canonical-run-id-or-null>
taskId: <canonical-task-id-or-null>
taskVersion: <canonical-task-version-or-null>
planRevision: <canonical-plan-revision-or-null>
configurationHash: <canonical-configuration-hash-or-null>
checkpointHash: <canonical-checkpoint-hash-or-null>
taskStatus: <canonical-task-status-or-null>
runStatus: <canonical-run-status-or-null>
phase: <canonical-phase-or-null>
---

# Goal

# Canonical Orchestration Checkpoint

# Verified Facts

# Changes

# Decisions

# Verification

# Next Action

# Blockers

# Assumptions

# Evidence

For `mode: orchestrated`, set `owner` to the Orchestrator. Current canonical fields must match the last committed checkpoint. The top-level `status` describes only this handoff lifecycle.
