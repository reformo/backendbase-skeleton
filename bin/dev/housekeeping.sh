#!/usr/bin/env bash
set -Eeuo pipefail

projectRoot="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$projectRoot"

bin/backendbase clear-cache
composer run cs-fix src
composer run generate-example-api-spec
composer update

temporaryDirectory="$(mktemp -d "${TMPDIR:-/tmp}/backendbase-composer-review.XXXXXX")"

cleanupReview() {
    rm -rf -- "$temporaryDirectory"
}

trap cleanupReview EXIT

isolatedProject="$temporaryDirectory/project"
isolatedComposerHome="$temporaryDirectory/composer"
mkdir -p "$isolatedProject"
install -d -m 0700 "$isolatedComposerHome"
install -m 0600 resources/security/composer-dev-public-key.pem "$isolatedComposerHome/keys.dev.pub"
install -m 0600 resources/security/composer-tags-public-key.pem "$isolatedComposerHome/keys.tags.pub"
cp composer.json composer.lock "$isolatedProject/"
export COMPOSER_HOME="$isolatedComposerHome"

composer validate --strict --no-check-publish --no-plugins --no-scripts --working-dir="$isolatedProject"
composer audit --locked --no-interaction --no-plugins --no-scripts --working-dir="$isolatedProject"
composer install \
    --working-dir="$isolatedProject" \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-cache \
    --no-plugins \
    --no-scripts

php bin/composer-supply-chain.php generate "$isolatedProject/vendor"
php bin/composer-supply-chain.php check "$isolatedProject/vendor"
printf 'Review composer.lock and both resources/security/composer-* evidence files before committing.\n'
