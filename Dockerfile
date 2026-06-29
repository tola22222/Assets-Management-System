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

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN composer dump-autoload --optimize
RUN php artisan package:discover --ansi

RUN chown -R www-data:www-data storage bootstrap/cache public \
  && chmod -R 755 public \
  && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]