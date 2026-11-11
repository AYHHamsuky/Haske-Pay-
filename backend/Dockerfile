# Haske Pay: PHP-FPM + nginx in one image; the same image runs web, queue and scheduler.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
COPY vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx supervisor icu-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_mysql bcmath intl zip opcache pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-haske.ini
COPY docker/supervisord.conf /etc/supervisord.conf
RUN composer dump-autoload --optimize 2>/dev/null || true \
 && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
