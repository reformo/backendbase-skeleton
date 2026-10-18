#!/usr/bin/env bash
set -Eeuo pipefail

projectRoot="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporaryDirectory="$(mktemp -d "${TMPDIR:-/tmp}/backendbase-build-test.XXXXXX")"
trap 'rm -rf -- "$temporaryDirectory"' EXIT
fixture="$temporaryDirectory/project"
fakeBin="$temporaryDirectory/bin"
revision='3333333333333333333333333333333333333333'

mkdir -p "$fixture/bin/deployment" "$fixture/deployment" "$fixture/resources/database/Migrations" \
    "$fixture/resources/security" "$fixture/vendor/bin" "$fakeBin" "$temporaryDirectory/unpacked"
cp "$projectRoot/bin/deployment/build-release.sh" "$projectRoot/bin/deployment/lib.sh" "$fixture/bin/deployment/"
cp "$projectRoot/deployment/release.json" "$fixture/deployment/"
cp "$projectRoot"/resources/database/Migrations/Version*.php "$fixture/resources/database/Migrations/"
printf '{}\n' > "$fixture/composer.lock"
printf '{}\n' > "$fixture/resources/security/composer-sbom.cdx.json"
printf '{}\n' > "$fixture/resources/security/composer-package-content.json"
printf '<?php\n' > "$fixture/bin/composer-supply-chain.php"
printf '#!/usr/bin/env bash\nexit 0\n' > "$fixture/vendor/bin/php-openapi"
printf '#!/usr/bin/env bash\nexit 0\n' > "$fakeBin/composer"
cat > "$fakeBin/git" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
case "$1" in
    rev-parse) printf '%s\n' "$TEST_BUILD_REVISION" ;;
    diff) exit 0 ;;
    archive) tar -cf - -C "$TEST_BUILD_FIXTURE" . ;;
    *) exit 1 ;;
esac
EOF
chmod +x "$fakeBin/composer" "$fakeBin/git" "$fixture/vendor/bin/php-openapi"

PATH="$fakeBin:$PATH" TEST_BUILD_REVISION="$revision" TEST_BUILD_FIXTURE="$fixture" \
    bash "$fixture/bin/deployment/build-release.sh" "$revision" "$temporaryDirectory/output"
tar -xzf "$temporaryDirectory/output/backendbase-$revision.tar.gz" -C "$temporaryDirectory/unpacked"
php -r '
    $source = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    if ($manifest["migrationTarget"] !== $source["migration-target"]
        || $manifest["applicationRollbackSafe"] !== $source["application-rollback-safe"]
        || $manifest["revision"] !== $argv[3]
        || array_key_exists("migration-target", $manifest)
        || array_key_exists("application-rollback-safe", $manifest)) {
        exit(1);
    }
' "$fixture/deployment/release.json" "$temporaryDirectory/unpacked/release-manifest.json" "$revision"

printf 'Release configuration and manifest compatibility tests passed.\n'
