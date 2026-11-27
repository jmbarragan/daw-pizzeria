# Imagen de La Focaccia di Trevi: PHP 8.4 con Apache, las dependencias de
# Composer y los estilos ya compilados. Con la misma imagen funcionan la web,
# la cola de correos y el planificador (compose.yaml).

# 1. Estilos y JavaScript con Vite: dejan public/build
FROM node:22.16-alpine AS estilos
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# 2. La carpeta vendor, que se copia a la imagen final
FROM composer:2.8 AS dependencias
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

# 3. La aplicación
FROM php:8.4-apache

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl bcmath zip opcache

# Apache sirve la carpeta public y deja pasar las direcciones de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html
COPY --from=dependencias /usr/bin/composer /usr/bin/composer
COPY --from=dependencias /app/vendor ./vendor
COPY . .
COPY --from=estilos /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/app/arrancar.sh

EXPOSE 80
CMD ["docker/app/arrancar.sh"]
