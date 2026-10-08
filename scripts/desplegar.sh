#!/usr/bin/env bash
#
# Actualiza el sistema en el servidor con lo último de GitHub (rama main).
#
# Uso, en el VPS:
#
#   cd /var/www/electro_hogar
#   bash scripts/desplegar.sh
#
# Sigue el orden de docs/DESPLIEGUE.md: la copia primero; lo lento (composer,
# npm) con el sitio todavía en pie; y el corte —modo mantenimiento— solo
# para migrar y rehacer las cachés, que son segundos.
#
# Variables opcionales:
#   USUARIO_WEB=www-data   dueño de storage/ y bootstrap/cache (el de PHP-FPM)
#   SALTAR_COPIA=1         seguir aunque la copia de seguridad falle

set -euo pipefail

cd "$(dirname "$0")/.."
RAIZ="$(pwd)"
USUARIO_WEB="${USUARIO_WEB:-www-data}"

paso() { printf '\n\033[1;34m== %s\033[0m\n' "$1"; }
aviso() { printf '\033[1;33mAVISO:\033[0m %s\n' "$1"; }

paso "Proyecto: $RAIZ"
git log -1 --oneline

# ---------------------------------------------------------------------------
paso "1/5 Copia de seguridad de la base"
# Si algo sale mal, esta es la copia a la que se vuelve.
if ! php artisan backup:run --only-db; then
    if [[ "${SALTAR_COPIA:-0}" == "1" ]]; then
        aviso "la copia falló y se sigue porque SALTAR_COPIA=1."
    else
        echo "La copia falló. Revisa DB_DUMP_BINARY_PATH y spatie/laravel-backup,"
        echo "o vuelve a ejecutar con SALTAR_COPIA=1 si sabes lo que haces."
        exit 1
    fi
fi

# ---------------------------------------------------------------------------
paso "2/5 Código y dependencias (el sitio sigue en pie)"
# --ff-only: si el servidor tiene cambios propios en archivos versionados, se
# para aquí en vez de mezclar. Se ven con `git status`.
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# ---------------------------------------------------------------------------
paso "3/5 Corte: migraciones y cachés"
php artisan down --render=errors::503 --retry=15

# Si algo falla dentro del corte, se avisa y se levanta el sitio: una tienda
# caída es peor que un despliegue a medio revisar.
trap 'aviso "falló un paso con el sitio en mantenimiento; se levanta igual. Revisa el error de arriba."; php artisan up' ERR

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

# Los permisos, si se despliega como root: PHP-FPM tiene que poder escribir.
if [[ "$(id -u)" == "0" ]] && id "$USUARIO_WEB" >/dev/null 2>&1; then
    chown -R "$USUARIO_WEB":"$USUARIO_WEB" storage bootstrap/cache
fi

php artisan up
trap - ERR

# ---------------------------------------------------------------------------
paso "4/5 Procesos en segundo plano"
# queue:work guarda el código viejo en memoria: hay que reiniciarlo.
php artisan queue:restart || true

for servicio in ventas-queue ventas-schedule ventas-reverb; do
    if systemctl list-unit-files "${servicio}.service" >/dev/null 2>&1 \
        && systemctl list-unit-files "${servicio}.service" | grep -q "$servicio"; then
        sudo systemctl restart "$servicio" && echo "reiniciado: $servicio"
    else
        aviso "no existe el servicio $servicio (ver docs/DESPLIEGUE.md §4)."
    fi
done

# ---------------------------------------------------------------------------
paso "5/5 Comprobación"
URL="$(grep -E '^APP_URL=' .env | cut -d= -f2- | tr -d '"')"
if [[ -n "$URL" ]]; then
    printf 'Salud (%s/up): ' "$URL"
    curl -s -o /dev/null -w '%{http_code}\n' "$URL/up" || true
    printf 'API (%s/api/v1/version): ' "$URL"
    curl -s -H 'Accept: application/json' "$URL/api/v1/version" || true
    echo
fi

git log -1 --oneline
echo "Listo."
