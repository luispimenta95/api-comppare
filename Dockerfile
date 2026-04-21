FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install

COPY entrypoint.sh /entrypoint.sh

EXPOSE 8000

CMD ["/entrypoint.sh"]