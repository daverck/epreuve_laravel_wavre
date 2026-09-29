FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    libzip-dev \
    sqlite-dev \
    oniguruma-dev \
    mysql-client \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]