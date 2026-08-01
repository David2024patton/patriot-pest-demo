# ============================================================
# Patriot Pest Control — Production Dockerfile
# nginx + php-fpm in one container, non-root where possible
# ============================================================
FROM php:8.3-fpm-alpine

# Build deps for extensions + runtime packages
RUN apk add --no-cache sqlite-dev nginx supervisor \
    && docker-php-ext-install pdo pdo_sqlite opcache \
    && rm -rf /var/cache/apk/*

# Non-root app user
RUN addgroup -g 1001 -S ppc && adduser -u 1001 -S ppc -G ppc

WORKDIR /app

# Copy application
COPY --chown=ppc:ppc . .

# Writable dirs (777 so php-fpm www-data can create the SQLite db)
RUN mkdir -p storage/logs /run/nginx \
    && chown -R ppc:ppc storage database \
    && chmod -R 777 storage database

# PHP production config + opcache
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php-opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

# nginx config
COPY deploy/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisord config
COPY deploy/supervisord.conf /etc/supervisord.conf

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1/health || exit 1

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
