# =========================================================
# Stage 1: Composer dependencies (build stage)
# =========================================================
FROM composer:2.7 AS composer-deps
WORKDIR /app

COPY api/composer.json api/composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

# =========================================================
# Stage 2: Production runtime
# =========================================================
FROM php:8.2-apache AS production

LABEL maintainer="ProWay Lab <dev@prowaylab.com>"
LABEL version="2.0"

# System dependencies
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
    && rm -rf /var/lib/apt/lists/*

# OPcache + JIT configuration
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Apache configuration
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers expires

# Copy Composer vendor (from build stage)
COPY --from=composer-deps /app/vendor /var/www/html/api/vendor

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/health.php | grep -q '"status":"ok"' || exit 1

CMD ["apache2-foreground"]
