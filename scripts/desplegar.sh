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

# El paso 2 hace `git pull`, que puede reescribir este mismo archivo mientras
# bash lo va leyendo. Se ejecuta desde una copia temporal para que el pull no
# cambie el script a medio camino.
if [[ -z "${DESPLIEGUE_RAIZ:-}" ]]; then
    DESPLIEGUE_RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
    copia="$(mktemp)"
    cp "$0" "$copia"
    DESPLIEGUE_RAIZ="$DESPLIEGUE_RAIZ" exec bash "$copia" "$@"
fi

RAIZ="$DESPLIEGUE_RAIZ"
cd "$RAIZ"
USUARIO_WEB="${USUARIO_WEB:-www-data}"

paso() { printf '\n\033[1;34m== %s\033[0m\n' "$1"; }
aviso() { printf '\033[1;33mAVISO:\033[0m %s\n' "$1"; }

# Ejecutado como root, cada `php artisan` puede crear el log del día con dueño
# root, y entonces la web (PHP-FPM) ya no puede escribirlo: la página muestra
# «Permission denied». Por eso los permisos se dejan bien al empezar y al
# terminar —también si el script falla a medias—.
arreglar_permisos() {
    if [[ "$(id -u)" == "0" ]] && id "$USUARIO_WEB" >/dev/null 2>&1; then
        chown -R "$USUARIO_WEB":"$USUARIO_WEB" "$RAIZ/storage" "$RAIZ/bootstrap/cache"
        chmod -R ug+rwX "$RAIZ/storage" "$RAIZ/bootstrap/cache"
    fi
}
trap arreglar_permisos EXIT

paso "Proyecto: $RAIZ"
git log -1 --oneline
arreglar_permisos

# Una caché de paquetes de un `composer install` con dependencias de
# desarrollo hace que artisan busque paquetes que aquí no están
# (`Class "Laravel\Pail\PailServiceProvider" not found`). Se borra; composer la
# vuelve a generar en el paso 2.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

# ---------------------------------------------------------------------------
paso "1/5 Copia de seguridad de la base"
# Una ruta de Windows en DB_DUMP_BINARY_PATH (copiada del .env de desarrollo)
# hace fallar la copia con «C:/xampp/mysql/bin/mariadb-dump: not found».
RUTA_DUMP="$(grep -E '^DB_DUMP_BINARY_PATH=' .env | cut -d= -f2- | tr -d '"' || true)"
if [[ "$RUTA_DUMP" == *:* || "$RUTA_DUMP" == *\\* ]]; then
    echo "DB_DUMP_BINARY_PATH en .env es una ruta de Windows: $RUTA_DUMP"
    echo "En el servidor debe ser la carpeta de mariadb-dump/mysqldump, por ejemplo:"
    echo "  DB_DUMP_BINARY_PATH=$(dirname "$(command -v mariadb-dump || command -v mysqldump || echo /usr/bin/x)")"
    echo "Corrígelo, ejecuta 'php artisan config:clear' y vuelve a lanzar el script."
    exit 1
fi

# Composer primero, por si vendor/ quedó a medias: artisan no arranca sin él.
composer install --no-dev --optimize-autoloader --no-interaction --quiet

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
trap 'aviso "falló un paso con el sitio en mantenimiento; se levanta igual. Revisa el error de arriba."; arreglar_permisos; php artisan up' ERR

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

# Antes de abrir: PHP-FPM tiene que poder escribir logs y cachés.
arreglar_permisos

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
