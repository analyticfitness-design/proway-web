# =========================================================
# Stage 1: Node.js — Build frontend
#   - npm run build compila:
#       a) Nunjucks templates → HTML estáticos (root/*.html)
#       b) Vite → dist/ (CSS/JS con hash para cache-busting)
# =========================================================
FROM node:20-alpine AS frontend-build
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline

COPY scripts/      scripts/
COPY src/frontend/ src/frontend/
COPY postcss.config.js vite.config.js ./

RUN npm run build

# =========================================================
# Stage 2: Composer — PHP dependencies (no dev)
# =========================================================
FROM composer:2.8 AS composer-deps
WORKDIR /app

COPY api/composer.json api/composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

# =========================================================
# Stage 3: Production runtime — PHP 8.2 + Apache
# =========================================================
FROM php:8.2-apache AS production

LABEL maintainer="ProWay Lab <dev@prowaylab.com>"
LABEL version="2.0"

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libzip-dev \
        zip \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        opcache \
        zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

# PHP configuration: OPcache + JIT + APCu
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php.ini     /usr/local/etc/php/conf.d/prowaylab.ini

# Apache configuration
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers expires

# ── Copy built artifacts ──────────────────────────────────────────────────────

# PHP vendor (from Composer stage)
COPY --from=composer-deps /app/vendor /var/www/html/api/vendor

# Compiled HTML templates (from Node stage: login.html, portal.html, etc.)
COPY --from=frontend-build /app/*.html   /var/www/html/
COPY --from=frontend-build /app/admin/   /var/www/html/admin/

# Vite assets (from Node stage: dist/assets/*.{js,css})
COPY --from=frontend-build /app/dist/    /var/www/html/dist/

# Application source (API, static assets, config)
COPY api/       /var/www/html/api/
COPY images/    /var/www/html/images/
COPY .htaccess  /var/www/html/.htaccess

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

# Health check (uses existing api/health.php)
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl -sf http://localhost/api/health.php | grep -q '"status":"ok"' || exit 1

CMD ["apache2-foreground"]
