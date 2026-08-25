#!/usr/bin/env bash
set -Eeuo pipefail

scriptDirectory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=bin/deployment/lib.sh
source "$scriptDirectory/lib.sh"

if [[ "$#" -gt 1 ]]; then
    deployFail 'Usage: rollback-release.sh [allowed-revision]'
fi

requestedRevision="${1:-}"
deployRoot="${BACKENDBASE_DEPLOY_ROOT:-/opt/backendbase}"
currentLink="${BACKENDBASE_CURRENT_LINK:-$deployRoot/webroot/api}"
readinessUrl="${BACKENDBASE_READINESS_URL:-http://127.0.0.1/example-api/_status/ready}"
requireActivationHook="${BACKENDBASE_REQUIRE_ACTIVATION_HOOK:-true}"
statePath="$deployRoot/state/active-release.json"
hooksDirectory="$deployRoot/shared/hooks"
DEPLOYMENT_LOCK_PATH=''

cleanupRollback() {
    if [[ -n "$DEPLOYMENT_LOCK_PATH" && -d "$DEPLOYMENT_LOCK_PATH" ]]; then
        rmdir "$DEPLOYMENT_LOCK_PATH"
    fi
}

trap cleanupRollback EXIT
trap 'deployLog "Rollback interrupted."; exit 130' INT TERM
trap 'deployLog "Rollback failed at line $LINENO."' ERR

deployRequireCommand curl
deployRequireCommand php
deployValidatePaths "$deployRoot" "$currentLink"
mkdir -p "$deployRoot"
deployAcquireLock "$deployRoot/.deployment-lock"
deployRequireManagedLink "$currentLink"
deployRequireManagedLink "$deployRoot/previous"

[[ -L "$currentLink" ]] || deployFail 'There is no active managed release.'
[[ -f "$statePath" ]] || deployFail 'The active release state is unavailable.'
[[ "$requireActivationHook" == 'true' || "$requireActivationHook" == 'false' ]] || deployFail 'The activation hook policy is invalid.'
if [[ "$requireActivationHook" == 'true' && ! -x "$hooksDirectory/activate-release" ]]; then
    deployFail "Required deployment hook is unavailable: $hooksDirectory/activate-release"
fi
[[ "$(deployManifestValue "$statePath" applicationRollbackSafe)" == 'true' ]] \
    || deployFail 'The active schema does not permit application rollback.'

activeRelease="$(deployResolvePath "$currentLink")" || deployFail 'The active release link is broken.'
releasesDirectory="$deployRoot/releases"
deployRequireReleasePath "$activeRelease" "$releasesDirectory"
allowedReleaseState="$(deployManifestValue "$statePath" previousRelease)"
[[ -n "$allowedReleaseState" && -d "$allowedReleaseState" ]] || deployFail 'The recorded rollback release is unavailable.'
allowedRelease="$(deployResolvePath "$allowedReleaseState")" || deployFail 'The rollback release link is broken.'
deployRequireReleasePath "$allowedRelease" "$releasesDirectory"

allowedRevision="$(basename "$allowedRelease")"
[[ "$allowedRevision" =~ ^[0-9a-f]{40}$ ]] || deployFail 'The rollback release revision is invalid.'
if [[ -f "$allowedRelease/release-manifest.json" ]]; then
    manifestRevision="$(deployManifestValue "$allowedRelease/release-manifest.json" revision)"
    [[ "$manifestRevision" == "$allowedRevision" ]] || deployFail 'The rollback release manifest does not match its directory.'
fi
if [[ -n "$requestedRevision" && "$requestedRevision" != "$allowedRevision" ]]; then
    deployFail "Revision $requestedRevision is not the recorded rollback release."
fi

deployLog "Switching from $(basename "$activeRelease") to $allowedRevision"
deployAtomicLink "$activeRelease" "$deployRoot/previous"
deployAtomicLink "$allowedRelease" "$currentLink"

if deployRunHook "$hooksDirectory/activate-release" "$requireActivationHook" "$allowedRelease" "$activeRelease" \
    && deployCheckReadiness "$readinessUrl"; then
    migrationTarget="$(deployManifestValue "$statePath" migrationTarget)"
    schemaOwnerRevision="$(deployManifestValue "$statePath" schemaOwnerRevision)"
    php -r '
    $state = [
        "revision" => $argv[1],
        "releasePath" => $argv[2],
        "previousRelease" => $argv[3],
        "migrationTarget" => $argv[4],
        "applicationRollbackSafe" => true,
        "schemaOwnerRevision" => $argv[5],
    ];
    $temporaryPath = $argv[6] . ".new";
    file_put_contents(
        $temporaryPath,
        json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
    if (!rename($temporaryPath, $argv[6])) {
        exit(1);
    }
    ' "$allowedRevision" "$allowedRelease" "$activeRelease" "$migrationTarget" "$schemaOwnerRevision" "$statePath"
    deployLog "Rollback to $allowedRevision passed readiness checks."
    exit 0
fi

deployAtomicLink "$activeRelease" "$currentLink"
deployAtomicLink "$allowedRelease" "$deployRoot/previous"
deployRunHook "$hooksDirectory/activate-release" "$requireActivationHook" "$activeRelease" "$allowedRelease"
deployCheckReadiness "$readinessUrl" || deployFail 'Rollback failed, and the original release did not recover.'
deployFail 'Rollback failed. The original release was restored.'
