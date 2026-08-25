#!/usr/bin/env bash
cd /opt/backendbase/webroot/api
git stash
git pull
cp .env.stage .env
composer install --optimize-autoloader --no-dev
composer run generate-example-api-spec
bin/backendbase clear-cache
bin/doctrine migrations:migrate --no-interaction --no-all-or-nothing
bin/doctrine orm:clear-cache:query
bin/doctrine orm:clear-cache:metadata
bin/doctrine orm:clear-cache:result
