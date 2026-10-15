# Standalone container build; Production migrations must NEVER run on startup.
# Production DB/files remain external to the disposable container.
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        unzip git curl ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql pdo_pgsql pdo_sqlite zip intl mbstring gd bcmath \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .

# Deterministic installs; no third-party lifecycle scripts in image builds.
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-plugins --optimize-autoloader
RUN npm ci --ignore-scripts --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules
RUN php artisan storage:link \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 10000
# Safe container restart: no migrations, seeders, destructive resets or privileged shell.
# Production deployments must run reviewed migrations as a separate, gated operation.
CMD ["sh", "-c", "exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
