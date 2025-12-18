FROM php:8.2-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libcurl4-openssl-dev \
        curl \
        git \
        libzip-dev \
        unzip \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-install pdo_pgsql mbstring xml curl zip \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY server/composer.json server/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY server/ ./
RUN php artisan package:discover --ansi
COPY start.sh /start.sh

RUN chmod +x /start.sh \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080

CMD ["/start.sh"]
