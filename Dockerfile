# syntax=docker/dockerfile:1.7
# =====================================================================
# Laravel Request System - multi-stage image (Labs 4 and 8)
# =====================================================================
# Stage 1 "vendor": installs PHP dependencies exactly from composer.lock
#                   using the Composer image (build tools stay out of the
#                   runtime image).
# Stage 2 "app":    PHP-FPM runtime. Non-root (www-data), production php.ini,
#                   no .env file and no credentials inside the image.
# Stage 3 "web":    Unprivileged Nginx that serves ONLY public/ and forwards
#                   PHP requests to the app service.
#
# Frontend: the Blade views use inline CSS and do not load Vite assets, so
# no Node build stage is required. If Vite assets are added later, add a
# `node` stage that runs `npm ci && npm run build` from package-lock.json
# and copy public/build into the app and web stages.
#
# Build arguments are NON-SECRET release metadata only.
# =====================================================================

ARG PHP_IMAGE=php:8.4-fpm-alpine3.22
ARG COMPOSER_IMAGE=composer:2.8
ARG NGINX_IMAGE=nginxinc/nginx-unprivileged:1.28-alpine

# ---------------------------------------------------------------------
FROM ${COMPOSER_IMAGE} AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-req=ext-*
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------
FROM ${PHP_IMAGE} AS app

ARG APP_VERSION=""
ARG GIT_SHA=unknown
ARG BUILD_DATE=""
ARG LAB_FAULT=false

LABEL org.opencontainers.image.title="laravel-request-system-app" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${GIT_SHA}" \
      org.opencontainers.image.created="${BUILD_DATE}"

# PHP extensions needed by Laravel + MySQL; fcgi provides the FPM health check.
RUN apk add --no-cache fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql opcache \
 && apk del .build-deps \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/zz-app.ini   "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/zz-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/app/healthcheck.sh /usr/local/bin/app-healthcheck

WORKDIR /var/www/html

# Application code is owned by root and read-only for the service account.
COPY --chown=root:root . .
COPY --from=vendor --chown=root:root /app/vendor ./vendor

# Release metadata baked into the artifact (Lab 8: running revision check).
RUN set -eux; \
    if [ -n "${APP_VERSION}" ]; then echo "${APP_VERSION}" > VERSION; fi; \
    echo "${GIT_SHA}" > REVISION; \
    if [ -n "${BUILD_DATE}" ]; then echo "${BUILD_DATE}" > BUILD_DATE; fi; \
    rm -f .lab-fault; \
    if [ "${LAB_FAULT}" = "true" ]; then echo "controlled lab fault" > .lab-fault; fi; \
    rm -f .env bootstrap/cache/*.php; \
    php artisan package:discover --ansi; \
    chmod +x /usr/local/bin/app-entrypoint /usr/local/bin/app-healthcheck; \
    # Only storage/ and bootstrap/cache/ are writable, by www-data (mode 775, no world write)
    chown -R www-data:www-data storage bootstrap/cache; \
    find storage bootstrap/cache -type d -exec chmod 775 {} +; \
    find storage bootstrap/cache -type f -exec chmod 664 {} +

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=5 \
    CMD ["app-healthcheck"]

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]

# ---------------------------------------------------------------------
FROM ${NGINX_IMAGE} AS web

ARG APP_VERSION=""
ARG GIT_SHA=unknown
LABEL org.opencontainers.image.title="laravel-request-system-web" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${GIT_SHA}"

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
# Only the public/ directory is copied into the web server.
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=5 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/nginx-health || exit 1
