# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app
# dev-зависимости включены намеренно: тесты запускаются внутри этого же образа
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql pcntl opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor

# .env нужен бутстрапу тестов; боевые значения приходят переменными
# окружения из docker-compose и имеют приоритет над файлом
RUN cp .env.example .env \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
