FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor

RUN docker-php-ext-install pdo pdo_mysql

# PHP-FPM pool: límites de procesos y timeout por request
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

COPY --chown=www-data:www-data . /var/www/html
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
