ARG PHP_IMAGE=backendbase-coverage-php:8.5.10
FROM ${PHP_IMAGE}
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends default-mysql-client supervisor \
    && rm -rf /var/lib/apt/lists/*
