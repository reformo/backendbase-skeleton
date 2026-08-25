#!/usr/bin/env bash
set -Eeuo pipefail

projectRoot="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
deployScript="$projectRoot/bin/deployment/deploy-release.sh"
rollbackScript="$projectRoot/bin/deployment/rollback-release.sh"
temporaryDirectory="$(mktemp -d "${TMPDIR:-/tmp}/backendbase-deployment-test.XXXXXX")"
fakeBin="$temporaryDirectory/bin"

cleanupTests() {
    rm -rf -- "$temporaryDirectory"
}

trap cleanupTests EXIT

failTest() {
    printf 'Deployment test failed: %s\n' "$1" >&2
    exit 1
}

assertCurrentRelease() {
    local rootPath="$1"
    local expectedPath="$2"
    local currentPath
    local resolvedExpectedPath

    currentPath="$(php -r 'echo realpath($argv[1]);' "$rootPath/webroot/api")"
    resolvedExpectedPath="$(php -r 'echo realpath($argv[1]);' "$expectedPath")"
    [[ "$currentPath" == "$resolvedExpectedPath" ]] || failTest "Expected $resolvedExpectedPath, got $currentPath"
}

createCommands() {
    mkdir -p "$fakeBin"
    cat > "$fakeBin/composer" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
    cat > "$fakeBin/curl" <<'EOF'
#!/usr/bin/env bash
counterFile="${TEST_CURL_COUNTER:?}"
failureCount="${TEST_CURL_FAILURE_COUNT:-0}"
currentCount=0
if [[ -f "$counterFile" ]]; then
    currentCount="$(< "$counterFile")"
fi
printf '%s\n' "$((currentCount + 1))" > "$counterFile"
if [[ "$currentCount" -lt "$failureCount" ]]; then
    exit 22
fi
exit 0
EOF
    chmod +x "$fakeBin/composer" "$fakeBin/curl"
}

createRoot() {
    local rootPath="$1"
    local oldRevision="$2"
    local oldRelease="$rootPath/releases/$oldRevision"

    mkdir -p "$oldRelease" "$rootPath/shared/hooks" "$rootPath/webroot"
    printf 'BACKENDBASE_ENV=production\n' > "$rootPath/shared/.env.production"
    printf '{"revision":"%s"}\n' "$oldRevision" > "$oldRelease/release-manifest.json"
    ln -s "$oldRelease" "$rootPath/webroot/api"

    cat > "$rootPath/shared/hooks/pre-migrate" <<'EOF'
#!/usr/bin/env bash
printf 'backup\n' >> "${TEST_HOOK_LOG:?}"
EOF
    cat > "$rootPath/shared/hooks/activate-release" <<'EOF'
#!/usr/bin/env bash
printf 'activate:%s\n' "$1" >> "${TEST_HOOK_LOG:?}"
EOF
    chmod +x "$rootPath/shared/hooks/pre-migrate" "$rootPath/shared/hooks/activate-release"
}

createArtifact() {
    local artifactDirectory="$1"
    local revision="$2"
    local fixturePath="$temporaryDirectory/fixture-$revision"
    local archivePath="$artifactDirectory/backendbase-$revision.tar.gz"
    local composerLockSha256
    local archiveSha256

    mkdir -p "$fixturePath/bin" "$fixturePath/vendor" "$artifactDirectory"
    printf '{}\n' > "$fixturePath/composer.lock"
    printf '<?php\n' > "$fixturePath/vendor/autoload.php"
    composerLockSha256="$(php -r 'echo hash_file("sha256", $argv[1]);' "$fixturePath/composer.lock")"
    printf '{"revision":"%s","composerLockSha256":"%s","migrationTarget":"Backendbase\\\\Migrations\\\\Version20260825050000","applicationRollbackSafe":true}\n' \
        "$revision" "$composerLockSha256" > "$fixturePath/release-manifest.json"

    cat > "$fixturePath/bin/doctrine" <<'EOF'
#!/usr/bin/env bash
if [[ "$*" == *'migrations:status'* ]]; then
    printf 'status\n'
    exit 0
fi
if [[ "$*" == *'--dry-run'* ]]; then
    printf 'SELECT 1;\n'
    exit 0
fi
if [[ "$*" == *'migrations:migrate'* && "${TEST_MIGRATION_FAILURE:-false}" == 'true' ]]; then
    printf 'migration failed\n' >&2
    exit 1
fi
exit 0
EOF
    cat > "$fixturePath/bin/backendbase" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
    chmod +x "$fixturePath/bin/doctrine" "$fixturePath/bin/backendbase"

    tar -czf "$archivePath" -C "$fixturePath" .
    archiveSha256="$(php -r 'echo hash_file("sha256", $argv[1]);' "$archivePath")"
    printf '%s  %s\n' "$archiveSha256" "$(basename "$archivePath")" > "$archivePath.sha256"
    printf '%s\n' "$archivePath"
}

runDeployment() {
    local rootPath="$1"
    local archivePath="$2"

    PATH="$fakeBin:$PATH" \
    TEST_CURL_COUNTER="$rootPath/curl-count" \
    TEST_HOOK_LOG="$rootPath/hook.log" \
    BACKENDBASE_DEPLOY_ROOT="$rootPath" \
    BACKENDBASE_CURRENT_LINK="$rootPath/webroot/api" \
    BACKENDBASE_READINESS_URL='http://readiness.test' \
    "$deployScript" "$archivePath" "$archivePath.sha256" '.env.production'
}

createCommands
oldRevision='1111111111111111111111111111111111111111'
newRevision='2222222222222222222222222222222222222222'

successRoot="$temporaryDirectory/success-root"
createRoot "$successRoot" "$oldRevision"
successArchive="$(createArtifact "$temporaryDirectory/success-artifact" "$newRevision")"
TEST_CURL_FAILURE_COUNT=0 TEST_MIGRATION_FAILURE=false runDeployment "$successRoot" "$successArchive"
assertCurrentRelease "$successRoot" "$successRoot/releases/$newRevision"
grep --quiet '^backup$' "$successRoot/hook.log" || failTest 'The backup hook did not run.'

PATH="$fakeBin:$PATH" \
TEST_CURL_COUNTER="$successRoot/curl-count" \
TEST_CURL_FAILURE_COUNT=0 \
TEST_HOOK_LOG="$successRoot/hook.log" \
BACKENDBASE_DEPLOY_ROOT="$successRoot" \
BACKENDBASE_CURRENT_LINK="$successRoot/webroot/api" \
BACKENDBASE_READINESS_URL='http://readiness.test' \
"$rollbackScript" "$oldRevision"
assertCurrentRelease "$successRoot" "$successRoot/releases/$oldRevision"

migrationFailureRoot="$temporaryDirectory/migration-failure-root"
createRoot "$migrationFailureRoot" "$oldRevision"
migrationFailureArchive="$(createArtifact "$temporaryDirectory/migration-failure-artifact" "$newRevision")"
if TEST_CURL_FAILURE_COUNT=0 TEST_MIGRATION_FAILURE=true runDeployment "$migrationFailureRoot" "$migrationFailureArchive"; then
    failTest 'A failed migration did not stop deployment.'
fi
assertCurrentRelease "$migrationFailureRoot" "$migrationFailureRoot/releases/$oldRevision"
test -f "$migrationFailureRoot"/state/recovery/*/MIGRATION_FAILED \
    || failTest 'Migration recovery state was not written.'
grep --quiet '^backup$' "$migrationFailureRoot/hook.log" || failTest 'The migration ran without the backup hook.'

activationFailureRoot="$temporaryDirectory/activation-failure-root"
createRoot "$activationFailureRoot" "$oldRevision"
activationFailureArchive="$(createArtifact "$temporaryDirectory/activation-failure-artifact" "$newRevision")"
if TEST_CURL_FAILURE_COUNT=1 TEST_MIGRATION_FAILURE=false runDeployment "$activationFailureRoot" "$activationFailureArchive"; then
    failTest 'A failed activation was reported as successful.'
fi
assertCurrentRelease "$activationFailureRoot" "$activationFailureRoot/releases/$oldRevision"

printf 'Deployment and rollback tests passed.\n'
