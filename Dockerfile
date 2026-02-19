FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor

RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
