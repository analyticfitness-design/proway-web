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
# Stage 3: Production runtime — PHP 8.3 FPM + Nginx
# =========================================================
FROM php:8.3-fpm-alpine AS production

LABEL maintainer="ProWay Lab <dev@prowaylab.com>"
LABEL version="3.0"

# System dependencies + PHP extensions + Nginx + Supervisor
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        libzip-dev \
        zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        opcache \
        zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps \
    && mkdir -p /var/www/html /run/nginx /var/log/supervisor

# PHP configuration: OPcache + JIT + APCu
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php.ini     /usr/local/etc/php/conf.d/prowaylab.ini

# PHP-FPM pool: proceso dinámico + timeout
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 15'; \
    echo 'pm.start_servers = 3'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 8'; \
    echo 'pm.max_requests = 500'; \
    echo 'request_terminate_timeout = 60s'; \
} >> /usr/local/etc/php-fpm.d/www.conf

# Nginx + Supervisor configuration
COPY nginx.conf       /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

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
COPY index.php          /var/www/html/index.php
COPY deploy-webhook.php /var/www/html/deploy-webhook.php

# Set permissions
RUN chown -R nobody:nobody /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

# Health check (uses existing api/health.php)
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl -sf http://localhost/api/health.php | grep -q '"status":"ok"' || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
