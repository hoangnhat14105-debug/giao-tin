FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf

COPY render-apache.conf /etc/apache2/sites-available/000-default.conf
COPY render-php.ini /usr/local/etc/php/conf.d/zz-render.ini
COPY . /var/www/html/

EXPOSE 10000
