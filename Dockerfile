FROM php:8.3-apache

ENV PORT=10000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" dom gd mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html/

RUN composer install \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader \
        --no-interaction \
    && mkdir -p /var/www/html/uploads/ticket_attachments \
    && chmod 0755 /var/www/html/uploads

COPY docker/apache-app.conf /etc/apache2/conf-available/samadhan-app.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/samadhan.ini
COPY docker/entrypoint.sh /usr/local/bin/samadhan-entrypoint

RUN a2enconf samadhan-app \
    && chmod 0755 /usr/local/bin/samadhan-entrypoint

ENTRYPOINT ["/usr/local/bin/samadhan-entrypoint"]
CMD ["apache2-foreground"]
