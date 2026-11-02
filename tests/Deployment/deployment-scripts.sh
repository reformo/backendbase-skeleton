#!/usr/bin/env bash
set -Eeuo pipefail

projectRoot="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
deployScript="$projectRoot/bin/deployment/deploy-release.sh"
rollbackScript="$projectRoot/bin/deployment/rollback-release.sh"
temporaryDirectory="$(mktemp -d "${TMPDIR:-/tmp}/backendbase-deployment-test.XXXXXX")"
fakeBin="$temporaryDirectory/bin"
activeTestPid=''
barrierDirectory=''

cleanupTests() {
    if [[ -n "$activeTestPid" ]] && kill -0 "$activeTestPid" 2> /dev/null; then
        [[ -z "$barrierDirectory" ]] || touch "$barrierDirectory/release"
        kill -TERM "$activeTestPid" 2> /dev/null || true
        wait "$activeTestPid" 2> /dev/null || true
    fi
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
if [[ -n "${TEST_ACTIVATION_BARRIER:-}" ]]; then
    touch "$TEST_ACTIVATION_BARRIER/entered"
    while [[ ! -f "$TEST_ACTIVATION_BARRIER/release" ]]; do
        sleep 0.05
    done
fi
EOF
    chmod +x "$rootPath/shared/hooks/pre-migrate" "$rootPath/shared/hooks/activate-release"
}

createArtifact() {
    local artifactDirectory="$1"
    local revision="$2"
    local fixturePath="$temporaryDirectory/fixture-$revision"
    local archivePath="$artifactDirectory/backendbase-$revision.tar.gz"
    local composerLockSha256
    local composerPackageContentSha256
    local composerSbomSha256
    local archiveSha256

    mkdir -p "$fixturePath/bin/deployment" "$fixturePath/vendor" "$fixturePath/resources/security" "$artifactDirectory"
    printf '{}\n' > "$fixturePath/composer.lock"
    printf '{}\n' > "$fixturePath/resources/security/composer-sbom.cdx.json"
    printf '{}\n' > "$fixturePath/resources/security/composer-package-content.json"
    printf '<?php\n' > "$fixturePath/vendor/autoload.php"
    composerLockSha256="$(php -r 'echo hash_file("sha256", $argv[1]);' "$fixturePath/composer.lock")"
    composerSbomSha256="$(php -r 'echo hash_file("sha256", $argv[1]);' "$fixturePath/resources/security/composer-sbom.cdx.json")"
    composerPackageContentSha256="$(php -r 'echo hash_file("sha256", $argv[1]);' "$fixturePath/resources/security/composer-package-content.json")"
    printf '{"revision":"%s","composerLockSha256":"%s","composerSbomSha256":"%s","composerPackageContentSha256":"%s","migrationTarget":"Backendbase\\\\Migrations\\\\Version20260823215059","applicationRollbackSafe":true}\n' \
        "$revision" \
        "$composerLockSha256" \
        "$composerSbomSha256" \
        "$composerPackageContentSha256" \
        > "$fixturePath/release-manifest.json"

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
if [[ "$*" == *'migrations:migrate'* && -n "${TEST_MIGRATION_BARRIER:-}" ]]; then
    touch "$TEST_MIGRATION_BARRIER/entered"
    while [[ ! -f "$TEST_MIGRATION_BARRIER/release" ]]; do
        sleep 0.05
    done
fi
exit 0
EOF
    cat > "$fixturePath/bin/backendbase" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
    chmod +x "$fixturePath/bin/doctrine" "$fixturePath/bin/backendbase"
    cat > "$fixturePath/bin/deployment/assert-empty-database.php" <<'EOF'
<?php
exit(getenv('TEST_NONEMPTY_DATABASE') === 'true' ? 1 : 0);
EOF

    if [[ "${TEST_ROLLBACK_UNSAFE:-false}" == 'true' ]]; then
        php -r '$path=$argv[1]; $data=json_decode(file_get_contents($path),true); $data["applicationRollbackSafe"]=false; file_put_contents($path,json_encode($data));' "$fixturePath/release-manifest.json"
    fi

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
    "$deployScript" "$archivePath" "$archivePath.sha256" '.env.production' "${TEST_INSTALLATION_MODE:-update}"
}

createCommands
oldRevision='1111111111111111111111111111111111111111'
newRevision='2222222222222222222222222222222222222222'

successRoot="$temporaryDirectory/success-root"
createRoot "$successRoot" "$oldRevision"
successArchive="$(createArtifact "$temporaryDirectory/success-artifact" "$newRevision")"
TEST_CURL_FAILURE_COUNT=0 TEST_MIGRATION_FAILURE=false runDeployment "$successRoot" "$successArchive"
assertCurrentRelease "$successRoot" "$successRoot/releases/$newRevision"
php -r '$state=json_decode(file_get_contents($argv[1]),true); exit($state["revision"]===$argv[2] ? 0 : 1);' "$successRoot/state/active-release.json" "$newRevision" \
    || failTest 'Successful deployment did not record the active revision.'
test -f "$successRoot"/state/recovery/*/DEPLOYED || failTest 'Successful deployment did not record completion.'
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

createFreshRoot() {
    local rootPath="$1"
    createRoot "$rootPath" "$oldRevision"
    unlink "$rootPath/webroot/api"
    cat > "$rootPath/shared/hooks/prepare-maintenance" <<'EOF'
#!/usr/bin/env bash
printf 'maintenance\n' >> "${TEST_HOOK_LOG:?}"
EOF
    chmod +x "$rootPath/shared/hooks/prepare-maintenance"
}

freshArchive="$(TEST_ROLLBACK_UNSAFE=true createArtifact "$temporaryDirectory/fresh-artifact" '3333333333333333333333333333333333333333')"
unsafeRoot="$temporaryDirectory/unsafe-root"
createRoot "$unsafeRoot" "$oldRevision"
if runDeployment "$unsafeRoot" "$freshArchive"; then
    failTest 'Normal deployment accepted a rollback-unsafe release.'
fi
assertCurrentRelease "$unsafeRoot" "$unsafeRoot/releases/$oldRevision"

freshRoot="$temporaryDirectory/fresh-root"
createFreshRoot "$freshRoot"
TEST_INSTALLATION_MODE=--fresh-install runDeployment "$freshRoot" "$freshArchive"
assertCurrentRelease "$freshRoot" "$freshRoot/releases/3333333333333333333333333333333333333333"
php -r '$state=json_decode(file_get_contents($argv[1]),true); exit($state["applicationRollbackSafe"]===false ? 0 : 1);' "$freshRoot/state/active-release.json" \
    || failTest 'Fresh installation lost the rollback-unsafe policy.'
grep --quiet '^maintenance$' "$freshRoot/hook.log" || failTest 'The maintenance hook did not run.'

for failure in nonempty backup migration activation readiness existing; do
    failureRoot="$temporaryDirectory/fresh-$failure-root"
    createFreshRoot "$failureRoot"
    if [[ "$failure" == 'backup' ]]; then
        printf '#!/usr/bin/env bash\nexit 1\n' > "$failureRoot/shared/hooks/pre-migrate"
    fi
    if [[ "$failure" == 'activation' ]]; then
        printf '#!/usr/bin/env bash\nexit 1\n' > "$failureRoot/shared/hooks/activate-release"
    fi
    if [[ "$failure" == 'existing' ]]; then
        ln -s "$failureRoot/releases/$oldRevision" "$failureRoot/webroot/api"
    fi
    if TEST_INSTALLATION_MODE=--fresh-install \
        TEST_NONEMPTY_DATABASE="$([[ "$failure" == 'nonempty' ]] && echo true || echo false)" \
        TEST_MIGRATION_FAILURE="$([[ "$failure" == 'migration' ]] && echo true || echo false)" \
        TEST_CURL_FAILURE_COUNT="$([[ "$failure" == 'readiness' ]] && echo 1 || echo 0)" \
        runDeployment "$failureRoot" "$freshArchive"; then
        failTest "Fresh installation accepted $failure failure."
    fi
    if [[ "$failure" != 'existing' ]]; then
        [[ ! -L "$failureRoot/webroot/api" ]] || failTest "The failed $failure installation remained active."
    fi
done

printf 'Fresh installation and maintenance failure tests passed.\n'

waitForBarrier() {
    local attempt
    for ((attempt = 0; attempt < 200; attempt++)); do
        [[ ! -f "$barrierDirectory/entered" ]] || return 0
        kill -0 "$activeTestPid" 2> /dev/null || failTest 'The deployment ended before the test barrier.'
        sleep 0.05
    done
    failTest 'The test barrier was not reached.'
}

interruptProcess() {
    local status=0
    kill -TERM "$activeTestPid"
    touch "$barrierDirectory/release"
    wait "$activeTestPid" || status=$?
    activeTestPid=''
    [[ "$status" -eq 130 ]] || failTest "Expected interrupted exit 130, got $status."
}

migrationInterruptRoot="$temporaryDirectory/interrupted-migration-root"
createRoot "$migrationInterruptRoot" "$oldRevision"
mkdir -p "$migrationInterruptRoot/state"
printf '{"revision":"%s"}\n' "$oldRevision" > "$migrationInterruptRoot/state/active-release.json"
migrationInterruptArchive="$(createArtifact "$temporaryDirectory/interrupted-migration-artifact" "$newRevision")"
barrierDirectory="$migrationInterruptRoot/barrier"
mkdir "$barrierDirectory"
PATH="$fakeBin:$PATH" \
TEST_CURL_COUNTER="$migrationInterruptRoot/curl-count" \
TEST_HOOK_LOG="$migrationInterruptRoot/hook.log" \
TEST_MIGRATION_BARRIER="$barrierDirectory" \
BACKENDBASE_DEPLOY_ROOT="$migrationInterruptRoot" \
BACKENDBASE_CURRENT_LINK="$migrationInterruptRoot/webroot/api" \
BACKENDBASE_READINESS_URL='http://readiness.test' \
"$deployScript" "$migrationInterruptArchive" "$migrationInterruptArchive.sha256" '.env.production' update &
activeTestPid=$!
waitForBarrier
assertCurrentRelease "$migrationInterruptRoot" "$migrationInterruptRoot/releases/$oldRevision"
interruptProcess
assertCurrentRelease "$migrationInterruptRoot" "$migrationInterruptRoot/releases/$oldRevision"
test -f "$migrationInterruptRoot"/state/recovery/*/migration-plan.sql || failTest 'Interrupted migration lost its plan.'
[[ ! -d "$migrationInterruptRoot/.deployment-lock" ]] || failTest 'Interrupted migration retained its lock.'
! test -f "$migrationInterruptRoot"/state/recovery/*/DEPLOYED || failTest 'Interrupted migration was marked complete.'

interruptRoot="$temporaryDirectory/interrupted-deployment-root"
createRoot "$interruptRoot" "$oldRevision"
mkdir -p "$interruptRoot/state"
printf '{"revision":"%s"}\n' "$oldRevision" > "$interruptRoot/state/active-release.json"
interruptArchive="$(createArtifact "$temporaryDirectory/interrupted-deployment-artifact" "$newRevision")"
barrierDirectory="$interruptRoot/barrier"
mkdir "$barrierDirectory"
PATH="$fakeBin:$PATH" \
TEST_CURL_COUNTER="$interruptRoot/curl-count" \
TEST_HOOK_LOG="$interruptRoot/hook.log" \
TEST_ACTIVATION_BARRIER="$barrierDirectory" \
BACKENDBASE_DEPLOY_ROOT="$interruptRoot" \
BACKENDBASE_CURRENT_LINK="$interruptRoot/webroot/api" \
BACKENDBASE_READINESS_URL='http://readiness.test' \
"$deployScript" "$interruptArchive" "$interruptArchive.sha256" '.env.production' update &
activeTestPid=$!
waitForBarrier
assertCurrentRelease "$interruptRoot" "$interruptRoot/releases/$newRevision"
interruptProcess
assertCurrentRelease "$interruptRoot" "$interruptRoot/releases/$newRevision"
php -r '$state=json_decode(file_get_contents($argv[1]),true); exit($state["revision"]===$argv[2] ? 0 : 1);' "$interruptRoot/state/active-release.json" "$oldRevision" \
    || failTest 'Interrupted deployment unexpectedly changed active-release state.'
[[ ! -d "$interruptRoot/.deployment-lock" ]] || failTest 'Interrupted deployment retained its lock.'
test -f "$interruptRoot"/state/recovery/*/previous-release || failTest 'Interrupted deployment lost its recovery record.'
! test -f "$interruptRoot"/state/recovery/*/DEPLOYED || failTest 'Interrupted deployment was marked complete.'

rollbackInterruptRoot="$temporaryDirectory/interrupted-rollback-root"
createRoot "$rollbackInterruptRoot" "$oldRevision"
rollbackInterruptArchive="$(createArtifact "$temporaryDirectory/interrupted-rollback-artifact" "$newRevision")"
runDeployment "$rollbackInterruptRoot" "$rollbackInterruptArchive"
barrierDirectory="$rollbackInterruptRoot/barrier"
mkdir "$barrierDirectory"
PATH="$fakeBin:$PATH" \
TEST_CURL_COUNTER="$rollbackInterruptRoot/curl-count" \
TEST_HOOK_LOG="$rollbackInterruptRoot/hook.log" \
TEST_ACTIVATION_BARRIER="$barrierDirectory" \
BACKENDBASE_DEPLOY_ROOT="$rollbackInterruptRoot" \
BACKENDBASE_CURRENT_LINK="$rollbackInterruptRoot/webroot/api" \
BACKENDBASE_READINESS_URL='http://readiness.test' \
"$rollbackScript" "$oldRevision" &
activeTestPid=$!
waitForBarrier
assertCurrentRelease "$rollbackInterruptRoot" "$rollbackInterruptRoot/releases/$oldRevision"
interruptProcess
assertCurrentRelease "$rollbackInterruptRoot" "$rollbackInterruptRoot/releases/$oldRevision"
php -r '$state=json_decode(file_get_contents($argv[1]),true); exit($state["revision"]===$argv[2] ? 0 : 1);' "$rollbackInterruptRoot/state/active-release.json" "$newRevision" \
    || failTest 'Interrupted rollback unexpectedly changed active-release state.'
[[ ! -d "$rollbackInterruptRoot/.deployment-lock" ]] || failTest 'Interrupted rollback retained its lock.'

printf 'Deployment and rollback interruption tests passed.\n'

bash "$projectRoot/tests/Deployment/build-release.sh"
