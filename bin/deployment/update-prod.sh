#!/usr/bin/env bash
set -Eeuo pipefail

scriptDirectory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "$#" -ne 2 ]]; then
    printf 'Usage: %s <release-archive> <checksum-file>\n' "$0" >&2
    exit 1
fi

export BACKENDBASE_DEPLOY_ROOT="${BACKENDBASE_DEPLOY_ROOT:-/opt/backendbase}"
export BACKENDBASE_CURRENT_LINK="${BACKENDBASE_CURRENT_LINK:-$BACKENDBASE_DEPLOY_ROOT/webroot/api}"
export BACKENDBASE_READINESS_URL="${BACKENDBASE_READINESS_URL:-http://127.0.0.1/example-api/_status/ready}"
export BACKENDBASE_REQUIRE_BACKUP_HOOK=true
export BACKENDBASE_REQUIRE_ACTIVATION_HOOK=true

exec "$scriptDirectory/deploy-release.sh" "$1" "$2" '.env.production'
