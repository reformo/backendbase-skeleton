# Claude Code Project Instructions

@AGENTS.md

## Claude Code Configuration

Use the Claude Code configuration described in the imported agent workflow.
Project model defaults live in [.claude/settings.json](.claude/settings.json).
Use the five project definitions under [.claude/agents](.claude/agents).

The main agent controls every stage transition and all agent calls.
Subagents perform only their assigned roles.
Keep at most five subagents active.
Use `Phase: scope` and `Phase: audit` for the two `test_analyst` tasks.
