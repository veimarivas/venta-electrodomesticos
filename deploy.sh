#!/bin/bash

# Script de despliegue para Electro Hogar (Laravel)
# Servidor: /var/www/electro_hogar

set -e

echo "=== Electro Hogar - Despliegue ==="
echo ""

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Funciones
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[AVISO]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    log_error "No se encontró artisan. Ejecuta este script desde la raíz del proyecto Laravel."
    exit 1
fi

# 1. Modo mantenimiento
log_info "Activando modo mantenimiento..."
php artisan down --render="errors::503" --retry=60

# 2. Pull de cambios
log_info "Obteniendo últimos cambios..."
git pull origin main

# 3. Instalar dependencias de composer
log_info "Instalando dependencias de Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Ejecutar migraciones
log_info "Ejecutando migraciones..."
php artisan migrate --force

# 5. Limpiar y reconstruir caché
log_info "Limpiando caché..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

log_info "Reconstruyendo caché..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Optimizar
log_info "Optimizando aplicación..."
php artisan optimize

# 7. Compilar assets
log_info "Compilando assets..."
npm ci --only=production
npm run build

# 8. Configurar permisos
log_info "Configurando permisos..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 9. Desactivar modo mantenimiento
log_info "Desactivando modo mantenimiento..."
php artisan up

# 10. Reiniciar queue workers (si existen)
if pgrep -f "queue:work" > /dev/null; then
    log_info "Reiniciando workers de cola..."
    php artisan queue:restart
fi

echo ""
log_info "¡Despliegue completado exitosamente!"
echo ""
