#!/bin/bash

# Script de despliegue para Electro Hogar (Laravel)
# Servidor: /var/www/electro_hogar
#
# El orden importa y está explicado en docs/DESPLIEGUE.md:
#
#   1. Copia de seguridad y todo lo LENTO (git, composer, npm) con el sitio
#      todavía en pie. Si algo falla ahí —red, disco, una versión de Node—, la
#      tienda nunca se llegó a caer.
#   2. El corte, corto: mantenimiento, migrar y rehacer las cachés.
#   3. Reiniciar los procesos que guardan el código viejo en memoria.
#
# Uso:  ./deploy.sh
# Desde: la raíz del proyecto Laravel, en el servidor.

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

# ============================================================================
# 1. Copia de seguridad y preparación (sitio todavía en pie)
# ============================================================================

log_info "Copia de seguridad de la base..."
php artisan backup:run || log_warn "La copia falló; revísala antes de continuar."

log_info "Obteniendo últimos cambios..."
git pull origin main

log_info "Instalando dependencias de Composer..."
# `--optimize-autoloader` rehace el mapa de clases: sin él, una clase nueva
# (un middleware, un componente) responde 500 hasta el siguiente despliegue.
composer install --no-dev --optimize-autoloader --no-interaction

# Los assets solo hacen falta si cambiaron Blade/SCSS/JS. Se compilan con el
# sitio en pie porque `npm ci` tarda minutos.
if [ "$1" != "--sin-assets" ]; then
    log_info "Compilando assets..."
    npm ci --omit=dev
    npm run build
fi

# ============================================================================
# 2. Corte breve: mantenimiento, migraciones y cachés
# ============================================================================

log_info "Activando modo mantenimiento..."
php artisan down --render="errors::503" --retry=60

log_info "Ejecutando migraciones..."
php artisan migrate --force

log_info "Rehaciendo cachés (config, rutas y vistas)..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

log_info "Ajustando permisos..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

log_info "Desactivando modo mantenimiento..."
php artisan up

# ============================================================================
# 3. Procesos que hay que reiniciar para que suelten el código viejo
# ============================================================================

log_info "Reiniciando workers de cola (si están corriendo)..."
if pgrep -f "queue:work" > /dev/null; then
    php artisan queue:restart
else
    log_warn "No hay un worker de cola corriendo; los avisos no se enviarán hasta que lo arranques."
fi

# Reverb y el planificador también cargan el código en memoria. No hay un
# `artisan` para reiniciarlos: van como servicio (systemd/Supervisor/NSSM).
# Si están registrados con estos nombres, se reinician solos; si no, esto solo
# lo recuerda.
for servicio in ventas-reverb ventas-schedule; do
    if command -v systemctl > /dev/null 2>&1 && systemctl list-unit-files | grep -q "${servicio}.service"; then
        log_info "Reiniciando ${servicio}..."
        sudo systemctl restart "${servicio}"
    else
        log_warn "Reinicia el proceso ${servicio} a mano: mantiene el código anterior en memoria."
    fi
done

echo ""
log_info "¡Despliegue completado exitosamente!"
log_warn "Comprueba que responde:  curl -s -o /dev/null -w '%{http_code}\\n' http://<servidor>/ -  (302 = arriba, 503 = sigue en mantenimiento)"
echo ""
