FROM php:8.3-fpm

# ═══════════════════════════════════════════════════════════
# Backend Dockerfile - Optimizado para rendimiento
# ═══════════════════════════════════════════════════════════

# ─── Instalar dependencias del sistema ───
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

# ─── Configurar extensiones de imagen ───
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# ─── Instalar extensiones PHP necesarias ───
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    bcmath \
    opcache

# ─── Copiar configuración de PHP optimizada ───
COPY docker/php.ini /usr/local/etc/php/conf.d/docker-php-config.ini

# ─── Instalar Composer ───
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ═══════════════════════════════════════════════════════════
# Configurar directorio de trabajo
# ═══════════════════════════════════════════════════════════
WORKDIR /var/www/html

# Copiar composer files primero (para cache de Docker layers)
COPY composer.json composer.lock ./

# Instalar dependencias de Composer con optimizaciones
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev \
    --no-scripts \
    && composer clear-cache

# Copiar el resto del proyecto
COPY . .

# Ejecutar scripts de composer post-install
RUN composer dump-autoload --optimize

# ─── Crear directorios necesarios y permisos ───
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ─── Script de inicio ───
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

# ═══════════════════════════════════════════════════════════
# Comando de inicio con optimizaciones
# ═══════════════════════════════════════════════════════════
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

