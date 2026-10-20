#!/usr/bin/env bash
set -euo pipefail
mkdir -p /run/rehearsal /run/php-conf /run/backendbase /srv/backendbase/shared/hooks
install --mode=0600 --owner=root --group=root /input/runtime.env /run/backendbase/runtime.env
for module in mysqlnd pdo xml bcmath ctype curl dom fileinfo iconv igbinary intl mbstring msgpack pdo_mysql phar posix simplexml sockets tokenizer xmlreader xmlwriter redis; do
    for configuration in /etc/php/8.5/cli/conf.d/*-"$module".ini; do
        cp -L "$configuration" /run/php-conf/
    done
done
printf '# Runtime settings are injected outside the release.\n' > /srv/backendbase/shared/.env.production
for hook in prepare-maintenance pre-migrate activate-release; do
    ln -s /fixture/"$hook" /srv/backendbase/shared/hooks/"$hook"
done
printf '#!/usr/bin/env bash\nexec php /input/composer.phar "$@"\n' > /usr/local/bin/composer
chmod +x /usr/local/bin/composer
exec /usr/bin/supervisord --configuration /fixture/supervisord.conf
