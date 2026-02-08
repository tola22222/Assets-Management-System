FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
  bash curl zip unzip git \
  libpng-dev libjpeg-turbo-dev freetype-dev \
  oniguruma-dev icu-dev \
  mysql-client

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd pdo pdo_mysql intl mbstring opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files first (for cache)
COPY composer.json composer.lock ./

# Install vendors without scripts
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy full app
COPY . .

# Run scripts now
RUN composer dump-autoload --optimize
RUN php artisan package:discover --ansi

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
