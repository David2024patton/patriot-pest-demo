FROM php:8.3-cli-alpine

RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /app

COPY . .

RUN mkdir -p storage/logs && chmod -R 777 storage database

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
