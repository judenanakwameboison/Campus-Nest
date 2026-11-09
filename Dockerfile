FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql \
    && php -m | grep -q '^fileinfo$' \
    && php -m | grep -q '^mbstring$' \
    && php -m | grep -q '^pdo_mysql$' \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod 0755 /usr/local/bin/docker-entrypoint.sh \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && printf '%s\n' 'upload_max_filesize=10M' 'post_max_size=12M' > /usr/local/etc/php/conf.d/uploads.ini \
    && test -d /var/www/html/assets/img/rooms \
    && chown www-data:www-data /var/www/html/assets/img/rooms

ENV PORT=10000 \
    BASE_PATH=/

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
