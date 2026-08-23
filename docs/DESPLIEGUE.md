# Despliegue y operación

> Cómo poner el sistema en el servidor de la tienda y mantenerlo vivo.
> Para el uso diario, ver [MANUAL.md](MANUAL.md). Para las decisiones de
> diseño, [PLAN.md](PLAN.md).

---

## 1. Qué hace falta en el servidor

| Pieza | Mínimo | Nota |
|---|---|---|
| PHP | 8.3 | Extensiones: `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `openssl` |
| MariaDB / MySQL | MariaDB 10.11 o MySQL 8 | El driver del `.env` es `mariadb`, no `mysql` |
| Composer | 2.x | |
| Node + npm | 22 / 11 | Solo para compilar los assets; no hace falta en tiempo de ejecución |
| Servidor web | Apache o Nginx | El *document root* apunta a `public/`, **nunca** a la raíz del proyecto |

> **El document root es `public/`.** Si se apunta a la raíz, `.env` queda
> descargable desde el navegador — con la contraseña de la base y la `APP_KEY`,
> que descifra sesiones y tokens. Es el fallo más caro y el más fácil de cometer.

---

## 2. Instalación

```bash
composer install --no-dev --optimize-autoloader
```

```bash
cp .env.example .env && php artisan key:generate
```

Rellenar el `.env` (está comentado campo por campo) y después:

```bash
php artisan migrate --force
```

```bash
php artisan db:seed
```

```bash
php artisan storage:link
```

```bash
npm ci && npm run build
```

El `db:seed` crea roles, permisos, cargos, el árbol de categorías, marcas,
productos de ejemplo y **dos cuentas de prueba**. Ver §6: hay que cambiarlas
antes de abrir la tienda.

### Datos de demostración (opcional)

Para enseñar el sistema o revisar los reportes con historia real:

```bash
php artisan db:seed --class=DemoSeeder
```

Genera doce meses de operación —compras recepcionadas, unidades con su kardex,
ventas repartidas por días, algunas anuladas— pasando por los **mismos
servicios** que usa la aplicación, así que los números cuadran igual que en
producción. Se niega a correr si ya hay compras o ventas, y también en
`APP_ENV=production`.

---

## 3. Ajustes de producción en el `.env`

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
SESSION_SECURE_COOKIE=true
REVERB_SCHEME=https
```

- **`APP_DEBUG=false` no es opcional.** Con `true`, cualquier error muestra la
  ruta del servidor, el contenido del `.env` y trozos de consulta al primer
  visitante que provoque una excepción.
- **`LOG_LEVEL=error`**: con `debug` el log guarda cada consulta y llena el
  disco en semanas.
- **`SESSION_SECURE_COOKIE=true`** solo si hay HTTPS; con `false` la cookie de
  sesión viaja en claro y basta estar en la misma red wifi para copiarla.

Después de cada cambio del `.env` o de un despliegue:

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> Y al revés: si algo deja de responder tras tocar el `.env`, casi siempre es
> la caché de configuración. `php artisan config:clear`.

---

## 4. Procesos que deben quedar corriendo

Tres, además del servidor web. En Windows se registran con **NSSM**; en Linux,
con Supervisor o systemd.

```bash
php artisan queue:work --tries=3
```
Manda los avisos push y las notificaciones. Si se cae, **las ventas se siguen
registrando** — solo dejan de llegar los avisos.

```bash
php artisan schedule:work
```
Dispara la copia de seguridad diaria y su vigilancia. **Sin este proceso no hay
copias**, y nadie se entera hasta el día que hay que restaurar.

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```
El WebSocket del panel «Ventas en vivo». Si se cae, la aplicación funciona
igual: solo ese panel se queda esperando.

> Los tres tienen que reiniciarse en cada despliegue: `queue:work` mantiene el
> código viejo en memoria y seguiría ejecutando la versión anterior.

### Reverb detrás del proxy

El navegador abre `wss://` sobre el puerto 443, no el 8080. En Nginx:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

Si el proxy termina el TLS, hay que declarar el `TrustProxies` correspondiente:
sin eso Laravel ve la petición como `http` y genera enlaces `http://` dentro de
una página `https://`, que el navegador bloquea como contenido mixto.

---

## 5. Copias de seguridad

`spatie/laravel-backup`, programado en `routes/console.php`:

| Hora | Tarea | Qué hace |
|---|---|---|
| 01:30 | `backup:clean` | Borra lo que ya no toca conservar |
| 02:00 | `backup:run` | Vuelca la base y comprime |
| 08:00 | `backup:monitor` | Avisa por correo si la última copia tiene más de un día |

**Qué se guarda:** el volcado completo de la base, las imágenes que subió el
usuario (`storage/app/public`) y el `.env`. El código no: vuelve del
repositorio, y meterlo llevaría también la plantilla Velzon entera —cientos de
megas al día que nadie conserva un mes.

**Retención:** 30 días de copias diarias completas; después una semanal, una
mensual y una anual.

**Comprobación manual:**

```bash
php artisan backup:run
```

```bash
php artisan backup:list
```

> **`mysqldump` tiene que estar accesible.** XAMPP no lo pone en el PATH; por
> eso existe `DB_DUMP_BINARY_PATH` en el `.env`. **Con barras normales**
> (`C:/xampp/mysql/bin`): entre comillas, dotenv interpreta `\x` como escape y
> el archivo entero deja de parsearse.

> **Las copias salen del servidor, o no son copias.** Tal como está, el ZIP se
> guarda en el mismo disco que la base: protege de un borrado accidental, no de
> que se queme el equipo. Copiarlas fuera es configurar un disco más en
> `config/backup.php` (`disks`) — y si salen del edificio, poner
> `BACKUP_ARCHIVE_PASSWORD`, porque el ZIP lleva dentro el `.env` con la
> `APP_KEY`.

### Restaurar

```bash
unzip 2026-08-16-02-00-00.zip -d restauracion
```

```bash
mysql -u root -p electronica_hogar < restauracion/db-dumps/mysql-electronica_hogar.sql
```

Después, devolver `restauracion/storage/app/public/` a su sitio y limpiar
cachés (`php artisan optimize:clear`).

> Una copia que nunca se ha restaurado no se sabe si sirve. Conviene probarla
> una vez, contra una base aparte, antes de necesitarla.

---

## 6. Antes de abrir la tienda

- [ ] `APP_DEBUG=false` y `APP_ENV=production`.
- [ ] **Cambiar la contraseña de `admin@electronicahogar.test` y borrar
      `vendedor@electronicahogar.test`.** Las trae el seeder con la contraseña
      `password` y están documentadas: dejarlas es dejar la puerta abierta.
- [ ] HTTPS con certificado válido.
- [ ] Comprobar que el document root es `public/`: abrir
      `https://…/.env` debe dar 404.
- [ ] Los tres procesos del §4 registrados como servicio, no lanzados a mano en
      una consola que se cierra al cerrar sesión.
- [ ] Correr `php artisan backup:run` una vez y comprobar que el ZIP existe.
- [ ] Restaurar esa copia en una base de pruebas.
- [ ] Configurar `MAIL_*` de verdad: con `MAIL_MAILER=log` nadie puede
      recuperar su contraseña ni enterarse de que el respaldo falló.

---

## 7. Comprobaciones rápidas

```bash
php artisan about
```

```bash
php artisan schedule:list
```

```bash
php artisan queue:failed
```

```bash
php artisan test
```

`/up` responde el estado de la aplicación: sirve para el monitor externo.

---

## 8. Lo que el sistema ya trae puesto

- **Cabeceras de seguridad** en todas las respuestas
  (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy`, y `Strict-Transport-Security` solo bajo HTTPS en
  producción). Están en `App\Http\Middleware\CabecerasDeSeguridad` y hay un
  test que las fija.
- **HTTPS forzado en las URLs generadas** cuando `APP_ENV=production`.
- **Límite de intentos:** 60 peticiones/minuto en la API, 5/minuto en el login
  —es la puerta por la que se prueban contraseñas—, y el throttling propio de
  Fortify en el login web.
- **Cuentas bloqueadas:** desactivar un usuario corta su sesión en la siguiente
  petición, no en el siguiente inicio de sesión.
- **Las ventas no se borran**, se anulan; los maestros usan borrado suave.
