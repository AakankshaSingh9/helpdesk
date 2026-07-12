# Railway production image — ONE service serving both the Laravel API and the
# built Vue SPA from a single origin. Same-origin keeps Sanctum's cookie auth
# first-party (no CORS, no cross-site-cookie problems), which is why this exists
# rather than two separate services. Built from the repo root so it can see both
# apps/web and apps/api.
#
# Stage 1 builds the SPA with VITE_API_URL="" — an empty base URL means the SPA
# calls /api/* on its own host (see apps/web/src/lib/api.ts). Stage 2 is the PHP
# API image (mirrors apps/api/Dockerfile.prod) with the SPA's dist dropped into
# Laravel's public/ so `artisan serve` hands it out.

# ── Stage 1: build the Vue SPA ──────────────────────────────────────────────
FROM node:22-alpine AS web
WORKDIR /web
COPY apps/web/package.json apps/web/package-lock.json ./
RUN npm ci
COPY apps/web/ ./
ENV VITE_API_URL=""
RUN npm run build

# ── Stage 2: the Laravel API (also serves the SPA) ──────────────────────────
FROM php:8.3-cli AS api

# System libs + the PHP extensions the app needs (Postgres, bcmath, zip).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libonig-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY apps/api/docker/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/api

# Install prod PHP deps first for better layer caching, then copy the app.
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY apps/api/ ./
RUN composer dump-autoload --optimize --no-dev \
    && chmod -R ug+rw storage bootstrap/cache

# Drop the built SPA into Laravel's public root. Hashed assets under
# /assets/* are served as static files; every other path falls through to
# Laravel, which returns index.html (see routes/web.php).
COPY --from=web /web/dist/ ./public/

# Railway injects the listen port via $PORT.
# On boot: cache config, apply the schema, ensure the admin login exists, serve.
# NOTE: intentionally no `route:cache` — routes use closures, which Laravel
# cannot serialize into a cached route file.
EXPOSE 8000
CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan db:seed --class='Database\\Seeders\\AdminUserSeeder' --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
