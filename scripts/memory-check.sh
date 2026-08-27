#!/bin/sh
set -eu

fail()
{
    printf '%s\n' "memory-check: $1" >&2
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

memory_root=.agents/memory

for directory in \
    "$memory_root/decisions" \
    "$memory_root/domains" \
    "$memory_root/handoffs" \
    "$memory_root/handoffs/archive" \
    "$memory_root/learnings" \
    "$memory_root/templates" \
    "$memory_root/runtime"
do
    require_directory "$directory"
done

for file in \
    "$memory_root/README.md" \
    "$memory_root/index.md" \
    "$memory_root/project.md" \
    "$memory_root/orchestration.md" \
    "$memory_root/decisions/README.md" \
    "$memory_root/domains/README.md" \
    "$memory_root/handoffs/README.md" \
    "$memory_root/handoffs/archive/README.md" \
    "$memory_root/learnings/README.md" \
    "$memory_root/templates/handoff.md" \
    "$memory_root/templates/decision.md" \
    "$memory_root/templates/learning.md" \
    "$memory_root/runtime/.gitignore"
do
    require_file "$file"
done

handoff_template=$memory_root/templates/handoff.md
for field in id status projectionStatus mode scope updated owner confidence runId taskId taskVersion planRevision configurationHash checkpointHash taskStatus runStatus phase
do
    require_field "$handoff_template" "$field"
done
for heading in "# Goal" "# Canonical Orchestration Checkpoint" "# Verified Facts" "# Changes" "# Decisions" "# Verification" "# Next Action" "# Blockers" "# Assumptions" "# Evidence"
do
    require_heading "$handoff_template" "$heading"
done

decision_template=$memory_root/templates/decision.md
for heading in "# Status" "# Date" "# Context" "# Decision" "# Consequences" "# Alternatives considered" "# Evidence" "# Review trigger"
do
    require_heading "$decision_template" "$heading"
done

learning_template=$memory_root/templates/learning.md
for heading in "# Problem" "# Cause" "# Resolution" "# Prevention" "# Evidence" "# Last verified date"
do
    require_heading "$learning_template" "$heading"
done

orchestration=$memory_root/orchestration.md
grep -Fq "Only the Orchestrator changes" "$orchestration" || fail "canonical role boundary is missing"
grep -Fq "memory update is not part of the canonical transaction" "$orchestration" || fail "projection failure boundary is missing"
grep -Fq "sole source of truth" "$orchestration" || fail "canonical source boundary is missing"

grep -Fqx '*' "$memory_root/runtime/.gitignore" || fail "runtime content is not ignored"
grep -Fqx '!.gitignore' "$memory_root/runtime/.gitignore" || fail "runtime .gitignore is not retained"

printf '%s\n' "memory-check: ok"
