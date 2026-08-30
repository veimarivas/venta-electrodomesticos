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

> **`public/assets/` casi no está en el repositorio.** Lo excluye `.gitignore`
> porque lleva la plantilla Velzon entera —cientos de megas comprados—, así que
> hay que copiarla a mano en cada instalación nueva.
>
> **Las imágenes de la marca sí van versionadas**, y llegan con el `git pull`:
>
> | Archivo | Para qué |
> |---|---|
> | `images/logo_hogar.png` | El original con el fondo recortado. No se sirve; es la fuente de los otros dos y del icono de la app |
> | `images/marca-login.png` | 478×357 — el logo del login |
> | `images/marca-sidebar.png` | 260×194 — el menú lateral y la barra superior |
>
> Se sacaron de la exclusión a propósito: sin ellas el login y el menú salen sin
> logo en cada despliegue, y copiarlas a mano cada vez es justo el paso que se
> olvida. La regla en `.gitignore` usa `/public/assets/*` y no el directorio a
> secas porque **git no entra en un directorio excluido**: sin ese `/*`, ningún
> `!` posterior podría volver a incluir nada de dentro.

### Actualizar una instalación que ya está en marcha

Subir los archivos **no basta**: el despliegue deja las rutas y la configuración
cacheadas (§4), y Laravel sigue leyendo esa copia hasta que se regenera. Una ruta
nueva responde **404 aunque su archivo ya esté en el servidor**.

El orden es: subir, migrar si hay tablas nuevas, y **rehacer las cachés**.

> **Lo lento va ANTES de bajar el sitio.** `composer install` y `npm ci` tardan
> minutos y son justo lo que puede fallar —red, disco, una versión de Node—. Si
> el sitio ya está en mantenimiento cuando revientan, la tienda se queda caída
> hasta que alguien se dé cuenta. Preparado todo, el corte real son los
> segundos que tardan la migración y las cachés.

Primero, con el sitio **todavía en pie**:

```bash
php artisan backup:run
```

```bash
git pull origin main && composer install --no-dev --optimize-autoloader && npm ci && npm run build
```

Y ahora el corte, corto:

```bash
php artisan down --render=errors::503
```

```bash
php artisan migrate --force && php artisan optimize:clear
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

```bash
php artisan up
```

> **Si algo falla entre el `down` y el `up`, el sitio se queda caído.** No es un
> fallo del servidor: es Laravel enseñando su página de mantenimiento, y se
> reconoce porque el 503 trae una página con estilos y el título *Service
> Unavailable* en vez del error escueto de nginx. Se sale con `php artisan up`.
>
> Y si `artisan` tampoco arranca —pasa cuando el `composer install` se cortó y
> `vendor/` quedó a medias—, el modo mantenimiento son **dos archivos** y se
> quitan a mano:
>
> ```bash
> rm -f storage/framework/down storage/framework/maintenance.php
> ```
>
> Es exactamente lo que hace `php artisan up`, sin necesitar que la aplicación
> arranque. Después, reinstalar dependencias con calma.

Para comprobar que volvió, **302** (redirige al login) y no 503:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://ventas.posgradosinnovaciencia.com/dashboard
```

Para comprobar que una ruta nueva llegó de verdad, sin entrar a la aplicación:

```bash
php artisan route:list --path=api/v1
```

Desde fuera, una ruta viva contesta **401** (existe, pide sesión) y una que falta
contesta **404**. Es la diferencia que distingue «no subí el código» de «no tengo
permiso»:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://ventas.posgradosinnovaciencia.com/api/v1/unidades/1/serial -H "Accept: application/json"
```

> **La app móvil no se actualiza sola con el servidor.** Si una versión del APK
> ya repartida usa un endpoint que todavía no subiste, esa pantalla falla y el
> resto sigue funcionando. Conviene **subir el backend antes** de repartir un
> APK nuevo: al revés, los teléfonos ven errores hasta que el servidor se pone
> al día.

### Vaciar los datos para empezar a probar

Deja la base como recién instalada **sin perder los accesos**: conserva roles,
permisos y **una sola cuenta**, la del administrador, con su rol. Borra todo lo
demás —catálogo, inventario, compras, ventas, personas, avisos y las sesiones
abiertas—.

```bash
php artisan backup:run
```

```bash
php artisan datos:limpiar
```

Antes de tocar nada enseña qué conserva y qué borra, con el recuento por tabla,
y pide **escribir el nombre de la base** para confirmar. No vale un sí: obliga a
mirar sobre cuál se está ejecutando, que es la diferencia entre vaciar la de
pruebas y vaciar la de la tienda.

| Opción | Para qué |
|---|---|
| `--admin=correo@ejemplo.com` | Conservar otra cuenta en vez de la del rol `admin` |
| `--force` | Saltarse la confirmación (guiones desatendidos) |

> **Es destructivo y no tiene vuelta atrás.** El comando recuerda hacer copia,
> pero no la hace solo a propósito: una copia automática antes de cada borrado
> da una falsa sensación de red, porque nadie comprueba que se pueda restaurar.
> Hazla tú y verifícala con `php artisan backup:list`.

> **Si no encuentra a quién conservar, no borra nada.** Sin ninguna cuenta con
> rol `admin` y sin `--admin`, se planta en vez de vaciar la base a ciegas y
> dejar el sistema sin forma de entrar.

> **Las cuentas borradas se llevan sus roles asignados.** Si quedaran las filas
> de `model_has_roles`, un usuario nuevo que reusara ese id heredaría permisos
> ajenos.

### Cuando el sitio entero devuelve 503

Hay dos 503 distintos y se distinguen a simple vista:

| Lo que se ve | Qué es | Se arregla con |
|---|---|---|
| Página con estilos, título *Service Unavailable* | **Modo mantenimiento de Laravel.** Nginx y PHP están bien | `php artisan up` |
| Página escueta de nginx, o *502 Bad Gateway* | PHP-FPM caído o sin responder | `sudo systemctl status php8.3-fpm` |

El primero es, con diferencia, el más frecuente: un despliegue que se cortó
entre el `php artisan down` y el `php artisan up`. Para verlo desde fuera sin
entrar al servidor:

```bash
curl -s -i https://ventas.posgradosinnovaciencia.com/ | head -5
```

Si el `Server:` dice nginx pero el cuerpo es la página de Laravel, es
mantenimiento. Antes de levantarlo conviene comprobar que el despliegue sí
terminó —si no, se estaría sirviendo una aplicación a medias—:

```bash
git log -1 --oneline && php artisan migrate:status | tail -15 && php artisan about | head -20
```

### Cuando alguien no puede entrar

«No puedo entrar» tiene cinco causas que desde el formulario se ven iguales: la
cuenta no existe, está desactivada, la contraseña no es la que se cree, el
intento está bloqueado por reintentos, o la caché de permisos quedó vieja.

```bash
php artisan usuario:acceso admin@ejemplo.com
```

Dice cuál de las cinco es. Si la cuenta no existe, **lista las que sí**, con su
rol y si están activas — que es lo que hace falta cuando uno no recuerda con
qué correo se creó.

Busca **igual que el formulario**: por correo o por nombre, pasando lo escrito a
minúsculas. Si buscara de otra forma diría que la cuenta existe mientras el
login la sigue rechazando, que es peor que no tener diagnóstico.

Para devolver el acceso:

```bash
php artisan usuario:acceso admin@ejemplo.com --reset
```

```bash
php artisan usuario:acceso admin@ejemplo.com --activar
```

> **La contraseña se teclea, no se pasa como argumento.** Escrita en la orden
> quedaría en el historial del intérprete y en la lista de procesos, donde la ve
> cualquiera con acceso al servidor. El comando la pide oculta y la confirma.

> **`--reset` también reactiva la cuenta.** Cambiar la clave de una cuenta
> desactivada dejaría a la persona igual de fuera, y ese viaje de ida y vuelta
> es justo lo que no hace falta cuando alguien está esperando para trabajar.

> **Si el aviso habla de esperar, es el límite de reintentos**, no la
> contraseña. El contador vive en la caché: `php artisan cache:clear`.

---

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
| 08:30 | `cuotas:avisar` | Avisa de las cuotas que vencen hoy y de las que se vencieron ayer |

> **`cuotas:avisar` también depende de `schedule:work`.** Sin ese proceso vivo
> la cartera se puede consultar en pantalla, pero nadie recibe el aviso, y una
> cuota que nadie mira se convierte en mora en silencio. Se puede lanzar a mano
> para comprobarlo:
>
> ```bash
> php artisan cuotas:avisar
> ```
>
> Contesta cuántas cuotas vencen hoy y cuántas se vencieron ayer. No lleva
> marca de «ya avisado» a propósito: el disparo son dos fechas exactas, así que
> repetirlo el mismo día vuelve a avisar de lo mismo y al día siguiente ya no.

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

## 7. La app del teléfono

La app móvil habla con este mismo servidor por `/api/v1`, con tokens de
Sanctum. No hay nada que instalar en el servidor para que funcione: la API ya
va montada con la aplicación web.

| | |
|---|---|
| Código | <https://github.com/veimarivas/venta-electrodomesticos-app> |
| En este equipo | `../electronica_hogar_app` |

> **Es un repositorio aparte del backend**, y a propósito: se compilan, se
> versionan y se reparten por caminos distintos —uno se despliega en el
> servidor, el otro se instala en teléfonos—. Lo que sí tienen que ir
> acompasados son las versiones: cada APK declara qué endpoints necesita.

**La dirección del servidor se congela al compilar el APK**, no es un ajuste
dentro de la app:

```bash
flutter build apk --release --dart-define=API_URL=https://ventas.posgradosinnovaciencia.com/api/v1
```

Si el dominio cambia, hay que **generar e instalar un APK nuevo**. El APK va
firmado con la clave de depuración, así que para reemplazarlo hay que desinstalar
antes el anterior. El detalle está en el README de la app.

Lo que este servidor tiene que cumplir para que el teléfono funcione:

- **`APP_URL` con la dirección pública y https.** De ahí salen las URL de las
  imágenes (QR de cobro, fotos de productos, logos). Con `APP_URL` apuntando a
  `localhost`, la app carga los datos pero las imágenes salen vacías.
- **Certificado válido.** Android rechaza un certificado caducado o autofirmado y
  la app solo dirá que no pudo conectar. El aviso de caducidad conviene tenerlo
  en el calendario.
- **Que `/api/v1/auth/login` devuelva JSON**, no el HTML de error de Laravel:

```bash
curl -s -X POST https://ventas.posgradosinnovaciencia.com/api/v1/auth/login -H "Accept: application/json" -d '{}'
```

Debe responder un JSON con `message` y `errors` (faltan `usuario`, `password` y
`dispositivo`). Si devuelve HTML, la app fallará con un error de red que no
explica nada.

> **Las notificaciones push están apagadas.** Falta el proyecto de Firebase; la
> app arranca igual y solo se queda sin avisos en el teléfono. El historial de
> avisos se lee por API y funciona. Para activarlas hacen falta el
> `google-services.json` en la app y `FIREBASE_CREDENTIALS` en este `.env`.

---

## 8. Comprobaciones rápidas

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

## 9. Lo que el sistema ya trae puesto

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
