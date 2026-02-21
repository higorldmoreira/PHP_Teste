# =============================================================================
# Multi-stage Dockerfile – PHP 8.2-FPM Alpine
# Laravel 11 + MySQL (pdo_mysql) + Redis + OPcache
#
# Stages:
#   vendor      → instala dependências Composer (sem dev)
#   base        → imagem PHP com todas as extensões
#   local       → dev local (sem cópia de código; use bind-mount)
#   production  → imagem final autocontida para produção
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1 – vendor: resolve dependências Composer
# -----------------------------------------------------------------------------
FROM composer:2.7 AS vendor

WORKDIR /app

# Copia apenas os manifests primeiro (melhor cache de camadas)
COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-autoloader \
        --prefer-dist

# Agora copia tudo e regenera o autoloader otimizado
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# -----------------------------------------------------------------------------
# Stage 2 – base: PHP-FPM Alpine com extensões necessárias
# -----------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS base

# UID/GID padrão usado pelo processo web (ajuste para coincidir com o host)
ARG UID=1000
ARG GID=1000

# Dependências de sistema (libs de compilação + runtime)
RUN apk add --no-cache \
        bash \
        curl \
        # GD
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        # ZIP
        libzip-dev \
        # Intl
        icu-dev \
        # Mbstring / Regex
        oniguruma-dev \
        # XML / SOAP
        libxml2-dev \
        libxslt-dev \
        # Shadow (usermod/groupmod)
        shadow \
    # Configura extensões que precisam de flags de compilação
    && docker-php-ext-configure gd \
            --with-freetype \
            --with-jpeg \
            --with-webp \
    # Instala extensões nativas em paralelo
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        mbstring \
        exif \
        pcntl \
        zip \
        intl \
        gd \
        opcache \
        xml \
        xsl \
        sockets \
    # Instala a extensão Redis via PECL
    && pecl install redis \
    && docker-php-ext-enable redis \
    # Limpeza (reduz tamanho final da imagem)
    && apk del --no-cache shadow \
    && rm -rf /tmp/pear /var/cache/apk/* /tmp/*

# Ajusta UID/GID do www-data para coincidir com o host (evita problemas de permissão)
RUN delgroup www-data 2>/dev/null || true \
    && deluser  www-data 2>/dev/null || true \
    && addgroup -g "${GID}" www-data \
    && adduser  -u "${UID}" -G www-data -s /bin/sh -D www-data

WORKDIR /var/www/html

EXPOSE 9000

# -----------------------------------------------------------------------------
# Stage 3 – local: apenas para desenvolvimento (sem cópia de código)
# O código chega via bind-mount no docker-compose.yml
# -----------------------------------------------------------------------------
FROM base AS local

# Instala ferramentas de debug apenas no ambiente local
RUN apk add --no-cache git unzip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/pear /var/cache/apk/*

USER www-data

CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Stage 4 – production: imagem autocontida e enxuta
# -----------------------------------------------------------------------------
FROM base AS production

# Copia código + vendor do stage "vendor"
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# Permissões mínimas para storage e bootstrap/cache
RUN chmod -R 775 /var/www/html/storage \
                 /var/www/html/bootstrap/cache

USER www-data

CMD ["php-fpm"]
