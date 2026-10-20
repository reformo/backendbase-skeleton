#!/usr/bin/env bash
set -euo pipefail
set -a
source /run/backendbase/runtime.env
set +a
export PHP_INI_SCAN_DIR=/run/php-conf
export PHPRC=/fixture/php.ini
if [[ "$1" == 'fpm' ]]; then
    exec /usr/sbin/php-fpm8.5 --nodaemonize --fpm-config /fixture/php-fpm.conf
fi
cd /srv/backendbase/webroot/api
if [[ "$1" == 'consumer' ]]; then
    exec runuser --user www-data -- php -d zend.exception_ignore_args=1 bin/backendbase queue:consume deployment-queue
fi
if [[ "$1" == 'relay' ]]; then
    exec runuser --user www-data -- php -d zend.exception_ignore_args=1 bin/backendbase outbox:relay --continuous --limit=100
fi
exit 1
