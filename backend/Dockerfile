FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libzip-dev libicu-dev unzip ca-certificates \
    && docker-php-ext-install pdo_pgsql intl zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p runtime storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && ln -s /var/www/html/runtime/app.env /var/www/html/.env \
    && chown -R www-data:www-data storage bootstrap/cache runtime

EXPOSE 9000
CMD ["php-fpm"]
