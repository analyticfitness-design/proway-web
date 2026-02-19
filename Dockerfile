FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor \
 && docker-php-ext-install pdo pdo_mysql \
 && mkdir -p /run/nginx

COPY --chown=www-data:www-data . /var/www/html/
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisord.conf

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
