#!/usr/bin/env bash
set -Eeuo pipefail

scriptDirectory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=bin/deployment/lib.sh
source "$scriptDirectory/lib.sh"

if [[ "$#" -ne 3 ]]; then
    deployFail 'Usage: deploy-release.sh <release-archive> <checksum-file> <environment-file-name>'
fi

archivePath="$1"
checksumPath="$2"
environmentFileName="$3"
deployRoot="${BACKENDBASE_DEPLOY_ROOT:-/opt/backendbase}"
currentLink="${BACKENDBASE_CURRENT_LINK:-$deployRoot/webroot/api}"
readinessUrl="${BACKENDBASE_READINESS_URL:-http://127.0.0.1/example-api/_status/ready}"
requireBackupHook="${BACKENDBASE_REQUIRE_BACKUP_HOOK:-true}"
requireActivationHook="${BACKENDBASE_REQUIRE_ACTIVATION_HOOK:-true}"
sharedDirectory="$deployRoot/shared"
releasesDirectory="$deployRoot/releases"
stateDirectory="$deployRoot/state"
hooksDirectory="$sharedDirectory/hooks"
stagingPath=''
DEPLOYMENT_LOCK_PATH=''

cleanupDeployment() {
    if [[ -n "$stagingPath" && -d "$stagingPath" ]]; then
        rm -rf -- "$stagingPath"
    fi
    if [[ -n "$DEPLOYMENT_LOCK_PATH" && -d "$DEPLOYMENT_LOCK_PATH" ]]; then
        rmdir "$DEPLOYMENT_LOCK_PATH"
    fi
}

trap cleanupDeployment EXIT
trap 'deployLog "Deployment interrupted."; exit 130' INT TERM
trap 'deployLog "Deployment failed at line $LINENO."' ERR

deployRequireCommand composer
deployRequireCommand curl
deployRequireCommand php
deployRequireCommand tar
deployValidatePaths "$deployRoot" "$currentLink"

[[ "$environmentFileName" =~ ^\.env\.[a-z0-9-]+$ ]] || deployFail 'The environment file name is invalid.'
archivePath="$(deployResolvePath "$archivePath")" || deployFail 'The release archive is unavailable.'
checksumPath="$(deployResolvePath "$checksumPath")" || deployFail 'The checksum file is unavailable.'

mkdir -p "$deployRoot" "$releasesDirectory" "$stateDirectory" "$(dirname "$currentLink")"
deployAcquireLock "$deployRoot/.deployment-lock"
deployRequireManagedLink "$currentLink"
deployRequireManagedLink "$deployRoot/previous"

environmentPath="$sharedDirectory/$environmentFileName"
[[ -f "$environmentPath" ]] || deployFail "The shared environment file is unavailable: $environmentPath"
[[ "$requireBackupHook" == 'true' || "$requireBackupHook" == 'false' ]] || deployFail 'The backup hook policy is invalid.'
[[ "$requireActivationHook" == 'true' || "$requireActivationHook" == 'false' ]] || deployFail 'The activation hook policy is invalid.'
if [[ "$requireBackupHook" == 'true' && ! -x "$hooksDirectory/pre-migrate" ]]; then
    deployFail "Required deployment hook is unavailable: $hooksDirectory/pre-migrate"
fi
if [[ "$requireActivationHook" == 'true' && ! -x "$hooksDirectory/activate-release" ]]; then
    deployFail "Required deployment hook is unavailable: $hooksDirectory/activate-release"
fi

expectedSha256="$(awk 'NR == 1 { print $1 }' "$checksumPath")"
[[ "$expectedSha256" =~ ^[0-9a-f]{64}$ ]] || deployFail 'The release checksum is invalid.'
actualSha256="$(deploySha256 "$archivePath")"
[[ "$actualSha256" == "$expectedSha256" ]] || deployFail 'The release archive checksum does not match.'

if ! tar -tzf "$archivePath" | awk '/^\// || /(^|\/)\.\.(\/|$)/ { exit 1 }'; then
    deployFail 'The release archive contains an unsafe path.'
fi

stagingPath="$releasesDirectory/.incoming-$$"
mkdir "$stagingPath"
tar -xzf "$archivePath" -C "$stagingPath"

manifestPath="$stagingPath/release-manifest.json"
[[ -f "$manifestPath" ]] || deployFail 'The release manifest is unavailable.'
revision="$(deployManifestValue "$manifestPath" revision)"
composerLockSha256="$(deployManifestValue "$manifestPath" composerLockSha256)"
migrationTarget="$(deployManifestValue "$manifestPath" migrationTarget)"
rollbackSafe="$(deployManifestValue "$manifestPath" applicationRollbackSafe)"

[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || deployFail 'The release revision is invalid.'
[[ "$composerLockSha256" =~ ^[0-9a-f]{64}$ ]] || deployFail 'The Composer lock checksum is invalid.'
[[ "$migrationTarget" =~ ^Backendbase\\Migrations\\Version[0-9]{14}$ ]] || deployFail 'The migration target is invalid.'
[[ "$rollbackSafe" == 'true' ]] || deployFail 'This release requires a maintenance deployment because application rollback is not schema-safe.'
[[ "$(deploySha256 "$stagingPath/composer.lock")" == "$composerLockSha256" ]] || deployFail 'composer.lock does not match the release manifest.'
[[ -f "$stagingPath/vendor/autoload.php" ]] || deployFail 'Production dependencies are absent from the release.'
[[ -x "$stagingPath/bin/doctrine" ]] || deployFail 'The Doctrine command is unavailable in the release.'
[[ -x "$stagingPath/bin/backendbase" ]] || deployFail 'The Backendbase command is unavailable in the release.'
[[ ! -e "$stagingPath/.env" && ! -L "$stagingPath/.env" ]] \
    || deployFail 'The release archive must not contain an environment file.'

releasePath="$releasesDirectory/$revision"
[[ ! -e "$releasePath" ]] || deployFail "The release already exists: $releasePath"
ln -s "$environmentPath" "$stagingPath/.env"
mkdir -p "$stagingPath/var/cache"

composer check-platform-reqs --no-dev --no-interaction --working-dir="$stagingPath"
mv "$stagingPath" "$releasePath"
stagingPath=''
manifestPath="$releasePath/release-manifest.json"

previousRelease=''
if [[ -L "$currentLink" ]]; then
    previousRelease="$(deployResolvePath "$currentLink")" || deployFail 'The active release link is broken.'
    deployRequireReleasePath "$previousRelease" "$releasesDirectory"
fi

recoveryDirectory="$stateDirectory/recovery/$(date -u '+%Y%m%dT%H%M%SZ')-$revision"
mkdir -p "$recoveryDirectory"
cp "$manifestPath" "$recoveryDirectory/release-manifest.json"
printf '%s\n' "$previousRelease" > "$recoveryDirectory/previous-release"

(
    cd "$releasePath"
    bin/doctrine migrations:status --no-interaction --no-ansi > "$recoveryDirectory/migrations-before.txt"
    bin/doctrine migrations:migrate "$migrationTarget" --dry-run --no-interaction --no-ansi \
        > "$recoveryDirectory/migration-plan.sql"
)

deployRunHook \
    "$hooksDirectory/pre-migrate" \
    "$requireBackupHook" \
    "$releasePath" \
    "$previousRelease" \
    "$migrationTarget" \
    "$recoveryDirectory"

if ! (
    cd "$releasePath"
    bin/doctrine migrations:migrate "$migrationTarget" --no-interaction --no-ansi --query-time
) > "$recoveryDirectory/migration.log" 2>&1; then
    touch "$recoveryDirectory/MIGRATION_FAILED"
    cat "$recoveryDirectory/migration.log" >&2
    deployFail "Migration failed. Use the recovery data in $recoveryDirectory."
fi

(
    cd "$releasePath"
    bin/backendbase clear-cache
    bin/doctrine migrations:up-to-date --fail-on-unregistered --no-interaction --no-ansi
)

if [[ -n "$previousRelease" ]]; then
    deployAtomicLink "$previousRelease" "$deployRoot/previous"
fi
deployAtomicLink "$releasePath" "$currentLink"

if ! deployRunHook "$hooksDirectory/activate-release" "$requireActivationHook" "$releasePath" "$previousRelease"; then
    activationSucceeded='false'
else
    activationSucceeded='true'
fi

if [[ "$activationSucceeded" == 'true' ]] && deployCheckReadiness "$readinessUrl"; then
    php -r '
    $state = [
        "revision" => $argv[1],
        "releasePath" => $argv[2],
        "previousRelease" => $argv[3],
        "migrationTarget" => $argv[4],
        "applicationRollbackSafe" => true,
        "schemaOwnerRevision" => $argv[1],
    ];
    $temporaryPath = $argv[5] . ".new";
    file_put_contents(
        $temporaryPath,
        json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
    if (!rename($temporaryPath, $argv[5])) {
        exit(1);
    }
    ' "$revision" "$releasePath" "$previousRelease" "$migrationTarget" "$stateDirectory/active-release.json"
    touch "$recoveryDirectory/DEPLOYED"
    deployLog "Activated release $revision"
    exit 0
fi

touch "$recoveryDirectory/ACTIVATION_FAILED"
if [[ -n "$previousRelease" ]]; then
    deployAtomicLink "$previousRelease" "$currentLink"
    deployRunHook "$hooksDirectory/activate-release" "$requireActivationHook" "$previousRelease" "$releasePath"
    deployCheckReadiness "$readinessUrl" || deployFail 'The new and previous releases both failed readiness checks.'
else
    unlink "$currentLink"
fi

deployFail "Activation failed. The application was returned to $previousRelease. The expanded schema remains in place."
