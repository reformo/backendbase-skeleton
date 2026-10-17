#!/bin/sh
set -eu

fail()
{
    printf '%s\n' "validate-agent-memory: $1" >&2
    exit 1
}

require_file()
{
    [ -f "$1" ] || fail "missing file: $1"
}

require_directory()
{
    [ -d "$1" ] || fail "missing directory: $1"
}

require_heading()
{
    grep -Fqx "$2" "$1" || fail "missing heading '$2' in $1"
}

require_field()
{
    grep -Eq "^$2:" "$1" || fail "missing front-matter field '$2' in $1"
}

memoryRoot=.agents/memory

for directory in \
    "$memoryRoot/decisions" \
    "$memoryRoot/domains" \
    "$memoryRoot/handoffs" \
    "$memoryRoot/handoffs/archive" \
    "$memoryRoot/learnings" \
    "$memoryRoot/templates" \
    "$memoryRoot/runtime"
do
    require_directory "$directory"
done

for file in \
    "$memoryRoot/README.md" \
    "$memoryRoot/index.md" \
    "$memoryRoot/project.md" \
    "$memoryRoot/orchestration.md" \
    "$memoryRoot/decisions/README.md" \
    "$memoryRoot/domains/README.md" \
    "$memoryRoot/handoffs/README.md" \
    "$memoryRoot/handoffs/archive/README.md" \
    "$memoryRoot/learnings/README.md" \
    "$memoryRoot/templates/handoff.md" \
    "$memoryRoot/templates/decision.md" \
    "$memoryRoot/templates/learning.md" \
    "$memoryRoot/runtime/.gitignore"
do
    require_file "$file"
done

handoffTemplate=$memoryRoot/templates/handoff.md
for field in id status projectionStatus mode scope updated owner confidence runId taskId taskVersion planRevision configurationHash checkpointHash taskStatus runStatus phase
do
    require_field "$handoffTemplate" "$field"
done
for heading in "# Goal" "# Canonical Orchestration Checkpoint" "# Verified Facts" "# Changes" "# Decisions" "# Verification" "# Next Action" "# Blockers" "# Assumptions" "# Evidence"
do
    require_heading "$handoffTemplate" "$heading"
done

decisionTemplate=$memoryRoot/templates/decision.md
for heading in "# Status" "# Date" "# Context" "# Decision" "# Consequences" "# Alternatives considered" "# Evidence" "# Review trigger"
do
    require_heading "$decisionTemplate" "$heading"
done

learningTemplate=$memoryRoot/templates/learning.md
for heading in "# Problem" "# Cause" "# Resolution" "# Prevention" "# Evidence" "# Last verified date"
do
    require_heading "$learningTemplate" "$heading"
done

orchestration=$memoryRoot/orchestration.md
grep -Fq "Only the Orchestrator changes" "$orchestration" || fail "canonical role boundary is missing"
grep -Fq "memory update is not part of the canonical transaction" "$orchestration" || fail "projection failure boundary is missing"
grep -Fq "sole source of truth" "$orchestration" || fail "canonical source boundary is missing"

grep -Fqx '*' "$memoryRoot/runtime/.gitignore" || fail "runtime content is not ignored"
grep -Fqx '!.gitignore' "$memoryRoot/runtime/.gitignore" || fail "runtime .gitignore is not retained"

printf '%s\n' "validate-agent-memory: ok"
