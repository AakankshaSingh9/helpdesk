# Laravel 12 API — PHP 8.4 (dev). Uses `php artisan serve` for V1.
FROM php:8.4-cli

# System libs needed to build the PHP extensions we use.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libonig-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (copied from the official image — no need to install it by hand).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# Composer needs a writable HOME for its cache when we run as UID 1000.
ENV COMPOSER_HOME=/tmp/composer

EXPOSE 8000

# On boot: install PHP deps (if missing), run migrations, then serve.
CMD ["sh", "-c", "composer install --no-interaction && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
