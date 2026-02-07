FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip zip \
    libpng-dev libzip-dev \
    libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
