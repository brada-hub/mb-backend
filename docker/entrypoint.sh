#!/bin/bash
# ═══════════════════════════════════════════════════════════
# Laravel Entrypoint Script - Optimizado para rendimiento
# ═══════════════════════════════════════════════════════════

set -e

echo "🚀 Iniciando Monster Band API..."

# Esperar a que PostgreSQL esté listo
echo "⏳ Verificando conexión a la base de datos..."
until php artisan db:monitor --max_attempts=30 2>/dev/null || timeout 30 bash -c 'until php -r "new PDO(\"pgsql:host=${DB_HOST:-postgres};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-monster_band}\", \"${DB_USERNAME:-monster_admin}\", \"${DB_PASSWORD:-MonsterBand2024!}\");" 2>/dev/null; do sleep 1; done'; do
    echo "⏳ Esperando a PostgreSQL..."
    sleep 2
done

echo "✅ Base de datos conectada"

# Ejecutar migraciones si es necesario
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "📦 Ejecutando migraciones..."
    php artisan migrate --force --no-interaction || true
fi

# Optimizaciones de Laravel para producción/desarrollo
echo "⚡ Aplicando optimizaciones..."

# Clear caches antiguos
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Cache de configuración (solo si no estamos en desarrollo con debug)
if [ "${APP_DEBUG:-false}" = "false" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    echo "✅ Cache de producción aplicado"
else
    echo "ℹ️  Modo desarrollo - sin cache de configuración"
fi

# Generar key si no existe
php artisan key:generate --no-interaction 2>/dev/null || true

# Crear enlace simbólico de storage si no existe
php artisan storage:link 2>/dev/null || true

echo "✅ Monster Band API listo!"
echo "🌐 Servidor iniciando en http://0.0.0.0:8000"

# Ejecutar el comando principal
exec "$@"
