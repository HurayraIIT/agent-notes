# Agent Notes container images. Two targets from one file:
#   app — php-fpm with the application and its Composer dependencies
#   web — nginx with one listener per scope (docker/nginx.conf); it serves no files, only FastCGI
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

FROM php:8.2-fpm-alpine AS app
RUN docker-php-ext-install pdo_mysql \
    && apk add --no-cache mariadb-client
WORKDIR /app
COPY --from=vendor /app/vendor /app/vendor
COPY . /app
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-agent-notes.ini
COPY docker/entrypoint.sh /usr/local/bin/agent-notes-entrypoint
RUN chmod 0755 /usr/local/bin/agent-notes-entrypoint \
    && rm -f /app/.env \
    && mkdir -p /app/storage/assets \
    && chown -R www-data:www-data /app/storage
ENTRYPOINT ["agent-notes-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx-app.conf /etc/nginx/agent-notes-app.conf
COPY --chmod=755 docker/nginx-robots.sh /docker-entrypoint.d/40-agent-notes-robots.sh
