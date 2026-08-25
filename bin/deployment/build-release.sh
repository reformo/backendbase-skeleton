#!/usr/bin/env bash
set -Eeuo pipefail

scriptDirectory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
projectRoot="$(cd "$scriptDirectory/../.." && pwd)"

# shellcheck source=bin/deployment/lib.sh
source "$scriptDirectory/lib.sh"

if [[ "$#" -ne 2 ]]; then
    deployFail 'Usage: build-release.sh <full-commit-sha> <output-directory>'
fi

expectedRevision="$1"
outputDirectory="$2"
temporaryDirectory=''

cleanupBuild() {
    if [[ -n "$temporaryDirectory" && -d "$temporaryDirectory" ]]; then
        rm -rf -- "$temporaryDirectory"
    fi
}

trap cleanupBuild EXIT
trap 'deployLog "Release build interrupted."; exit 130' INT TERM
trap 'deployLog "Release build failed at line $LINENO."' ERR

deployRequireCommand composer
deployRequireCommand git
deployRequireCommand php
deployRequireCommand tar

[[ "$expectedRevision" =~ ^[0-9a-f]{40}$ ]] || deployFail 'The release revision must be a full commit SHA.'

cd "$projectRoot"
actualRevision="$(git rev-parse HEAD)"
[[ "$actualRevision" == "$expectedRevision" ]] || deployFail "Checked-out revision $actualRevision does not match $expectedRevision."
git diff --quiet || deployFail 'The release working tree has unstaged changes.'
git diff --cached --quiet || deployFail 'The release working tree has staged changes.'

releaseConfiguration="$projectRoot/deployment/release.json"
[[ -f "$releaseConfiguration" ]] || deployFail 'deployment/release.json is unavailable.'
migrationTarget="$(deployManifestValue "$releaseConfiguration" migrationTarget)"
rollbackSafe="$(deployManifestValue "$releaseConfiguration" applicationRollbackSafe)"
[[ "$migrationTarget" =~ ^Backendbase\\Migrations\\Version[0-9]{14}$ ]] || deployFail 'The migration target is invalid.'
[[ "$rollbackSafe" == 'true' || "$rollbackSafe" == 'false' ]] || deployFail 'The rollback policy is invalid.'

latestMigration="$(printf '%s\n' resources/database/Migrations/Version*.php | sort | tail -1)"
latestMigration="Backendbase\\Migrations\\$(basename "$latestMigration" .php)"
[[ "$migrationTarget" == "$latestMigration" ]] || deployFail "The release target must be the latest migration: $latestMigration"

mkdir -p "$outputDirectory"
outputDirectory="$(cd "$outputDirectory" && pwd)"
temporaryDirectory="$(mktemp -d "${TMPDIR:-/tmp}/backendbase-release.XXXXXX")"
releaseRoot="$temporaryDirectory/release"
mkdir -p "$releaseRoot"

git archive "$expectedRevision" | tar -xf - -C "$releaseRoot"

composer install \
    --working-dir="$releaseRoot" \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --classmap-authoritative

(
    cd "$releaseRoot"
    composer run generate-example-api-spec
    composer run validate-example-api-spec
    vendor/bin/php-openapi validate --silent public/example-api/docs/example-api-merged.yml
)

composerLockSha256="$(deploySha256 "$releaseRoot/composer.lock")"
php -r '
    $configuration = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $manifest = [
        "revision" => $argv[3],
        "composerLockSha256" => $argv[4],
        "migrationTarget" => $configuration["migrationTarget"],
        "applicationRollbackSafe" => $configuration["applicationRollbackSafe"],
    ];
    file_put_contents(
        $argv[2],
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
' "$releaseRoot/deployment/release.json" "$releaseRoot/release-manifest.json" "$expectedRevision" "$composerLockSha256"

archiveName="backendbase-$expectedRevision.tar.gz"
archivePath="$outputDirectory/$archiveName"
tar -czf "$archivePath" -C "$releaseRoot" .
archiveSha256="$(deploySha256 "$archivePath")"
printf '%s  %s\n' "$archiveSha256" "$archiveName" > "$archivePath.sha256"

deployLog "Created $archivePath"
deployLog "SHA-256: $archiveSha256"
