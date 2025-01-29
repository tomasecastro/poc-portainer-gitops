
FROM composer:lts AS deps
WORKDIR /app
COPY composer.json /app/
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-dev --no-interaction --prefer-dist

FROM php:8.2-cli
WORKDIR /usr/src/myapp
COPY . .
COPY --from=deps /app/vendor /usr/src/myapp/vendor/

# Crear directorio public si no existe
RUN mkdir -p /usr/src/myapp/public

# Exponer puerto
EXPOSE 3000

# Ejecutar PHP embebido
CMD ["php", "-S", "0.0.0.0:3000", "-t", "/usr/src/myapp"]
