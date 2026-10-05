# PHP-FPM image for the Memo Test API (Laravel 8 + Lighthouse GraphQL).
# The source code is not copied: docker-compose mounts the repository, and
# _docker/app/entrypoint.sh installs dependencies and prepares the database.
FROM php:8.1-fpm-alpine

# UID/GID of the host user, so files created in the mounted repo (vendor/,
# storage/logs) belong to that user and not to root.
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache git unzip su-exec shadow \
    && docker-php-ext-install pdo_mysql \
    && usermod -u "${UID}" www-data \
    && groupmod -g "${GID}" www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY _docker/app/entrypoint.sh /usr/local/bin/memo-entrypoint

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/api-memo-test

ENTRYPOINT ["memo-entrypoint"]
CMD ["php-fpm"]
EXPOSE 9000
