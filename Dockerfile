# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4
ARG NODE_VERSION=24

FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        tzdata \
        mariadb-client \
        postgresql-client \
        sqlite \
    && install-php-extensions \
        bcmath \
        dom \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        posix \
        redis \
        xmlreader \
        zip \
    && rm /usr/local/bin/install-php-extensions \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --no-dev --optimize --no-scripts --no-interaction \
    && mkdir -p \
        storage/app/public \
        storage/backup \
        storage/bing-wallpaper \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && TELESCOPE_ENABLED=false php artisan package:discover \
    && composer check-platform-reqs --no-dev

FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund

COPY resources resources
COPY --from=vendor /var/www/html/vendor ./vendor

RUN npm run build

FROM base AS runtime

ARG APP_VERSION=dev
ARG VCS_REF=local

LABEL org.opencontainers.image.title="Laravel Backend Lab" \
      org.opencontainers.image.description="Laravel reference application with Filament, queues, scheduling, and Reverb" \
      org.opencontainers.image.source="https://github.com/haoyuqi/laravel-backend-lab" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${VCS_REF}"

ENV APP_ENV=production \
    APP_VERSION=${APP_VERSION} \
    LOG_CHANNEL=stderr \
    TELESCOPE_ENABLED=false \
    DEBUGBAR_ENABLED=false \
    TZ=Asia/Shanghai

COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/background.conf /etc/supervisor/background.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p \
        storage/app/public \
        storage/backup \
        storage/bing-wallpaper \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && php artisan filament:assets \
    && php artisan storage:link \
    && rm -f bootstrap/cache/*.php \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && mkdir -p /var/log/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx \
    && chmod 0755 /usr/local/bin/docker-entrypoint.sh

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Local source mounts use an explicit host uid/gid and development dependencies.
FROM runtime AS development

USER root
ARG DEV_UID=1000
ARG DEV_GID=1000
COPY --from=assets /usr/local/bin/node /usr/local/bin/node
COPY --from=assets /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=assets /app/node_modules /var/www/html/node_modules
RUN apk add --no-cache libstdc++ \
    && ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --prefer-dist --no-interaction \
    && chown -R "${DEV_UID}:${DEV_GID}" storage bootstrap/cache public vendor node_modules /var/lib/nginx /var/log/nginx
COPY docker/php-dev.ini /usr/local/etc/php/conf.d/zzz-development.ini
ENV APP_ENV=local APP_DEBUG=true HOME=/tmp
USER ${DEV_UID}:${DEV_GID}

# Keep the default build and release artifact production-only.
FROM runtime AS production
