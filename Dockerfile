FROM php:8.2-fpm-alpine

# Install system deps
RUN apk add --no-cache \
  bash curl zip unzip git \
  libpng-dev libjpeg-turbo-dev freetype-dev \
  oniguruma-dev icu-dev \
  mysql-client

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd pdo pdo_mysql intl mbstring opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer first (cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy Laravel app
COPY . .

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
