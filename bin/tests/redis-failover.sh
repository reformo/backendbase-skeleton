#!/usr/bin/env bash
set -euo pipefail

projectRoot="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$projectRoot"
export BACKENDBASE_REDIS_FAILOVER_PROJECT="backendbase-redis-failover-$(date +%s)-$$"
export BACKENDBASE_REDIS_FAILOVER_TESTS=1

cleanup() {
    local testStatus=$?
    if [ "$testStatus" -ne 0 ]; then
        docker compose --env-file /dev/null --project-name "$BACKENDBASE_REDIS_FAILOVER_PROJECT" \
            --file resources/testing/redis-failover/compose.yaml logs --no-color --tail 30 || testStatus=$?
    fi
    docker compose --env-file /dev/null --project-name "$BACKENDBASE_REDIS_FAILOVER_PROJECT" \
        --file resources/testing/redis-failover/compose.yaml down --volumes --remove-orphans --timeout 2
    return "$testStatus"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [ "$#" -eq 0 ]; then
    set -- vendor/bin/phpunit --no-coverage --do-not-record-test-run-history \
        src/Backendbase/Domain/IdentityAndAccess/Tests/Services/RedisFailoverTest.php
fi

"$@"
