# 1. Gunakan sistem operasi dengan PHP 8.2
FROM php:8.2-cli

# 2. Install perlengkapan dasar, database PostgreSQL, dan Node.js (untuk Vite/Tailwind)
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev zip unzip git curl \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql zip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 3. Masukkan Composer ke dalam sistem
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Tentukan folder kerja dan masukkan seluruh kode SkuySocial
WORKDIR /app
COPY . .

# 5. Install semua dependensi Laravel dan Build tampilan Tailwind
RUN composer install --optimize-autoloader --no-dev
RUN npm install
RUN npm run build

# 6. Perintah wajib untuk menyalakan server saat berhasil di-deploy
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}