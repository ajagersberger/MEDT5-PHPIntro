FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

ENV APP_ROOT /app
WORKDIR ${APP_ROOT}

# Diese Zeile ist nicht mehr nötig, da das Volume die Dateien bereitstellt:
# COPY ${APP_ROOT}/src /var/www/html
