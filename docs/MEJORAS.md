# Mejoras pendientes

> **Solo lo que falta**, en orden de lo que está en juego. Lo ya hecho, con su
> fecha y su porqué, está en [CHANGELOG.md](CHANGELOG.md): cuando algo de aquí
> se termina, se borra de esta lista y se anota allí.
>
> Revisado el 2026-10-06 sobre los dos repositorios (panel y app).

**Leyenda:** 🔴 riesgo real · 🟠 importante · 🟡 mejora · ⏸ esperar a que haga falta

---

## 1. Operación y seguridad del servidor

No es trabajo de código, y por eso va primero: construir encima de un sistema
sin copias es apilar trabajo sobre algo que puede desaparecer.

| | Qué | Por qué | Cómo |
|---|---|---|---|
| 🔴 | **Los tres procesos del servidor no están corriendo** | `systemctl` no encuentra `ventas-queue`, `ventas-schedule` ni `ventas-reverb`. Sin `schedule:work` **no se ejecuta ninguna copia de seguridad** ni `cuotas:avisar`; sin `queue:work` no salen los avisos encolados. | Registrarlos como servicio ([DESPLIEGUE.md §4](DESPLIEGUE.md)) y, al día siguiente, comprobar con `php artisan backup:list` que la copia existe. |
| 🔴 | **Las copias se guardan en el mismo disco** | Protegen de un borrado accidental, no de que se dañe el equipo. | Un disco remoto (S3, Backblaze B2 o SFTP) en `config/backup.php` y `BACKUP_ARCHIVE_PASSWORD`: el ZIP lleva el `.env` con la `APP_KEY`. Restaurar una vez en una base aparte. |
| 🔴 | **La API viaja por HTTP en claro** | La app usa `http://69.62.91.168:8010` con datos móviles: el token de Sanctum (acceso completo) y las contraseñas del login cruzan Internet sin cifrar. | Dominio + certificado (Let's Encrypt), Nginx con TLS, quitar la excepción de `network_security_config.xml` en la app, recompilar el APK y activar `SESSION_SECURE_COOKIE`. |
| 🟠 | **`APP_ENV` del servidor por confirmar** | `AppServiceProvider` fuerza URLs `https://` en producción, pero el servidor responde por http. Si está en `production`, imágenes y assets saldrían rotos; si no lo está, `APP_DEBUG` y el endurecimiento quizá tampoco. | Revisar el `.env` real. Se resuelve solo al pasar a HTTPS. |
| 🟠 | **Cuentas del seeder** | `admin@…` y `vendedor@…` con `password` están documentadas. | Confirmar que en producción se cambiaron/borraron ([DESPLIEGUE.md §6](DESPLIEGUE.md)). |

---

## 2. Lo que solo espera credenciales

El código está hecho; falta contratar o configurar.

| | Qué | Qué falta |
|---|---|---|
| 🟠 | **Push al teléfono con la app cerrada** | Proyecto de Firebase: `FIREBASE_CREDENTIALS` en el servidor y `google-services.json` en la app. `php artisan push:revisar` dice qué falta. Hoy suena gracias al sondeo de 15 s, solo con la app abierta. |
| 🟡 | **Avisos al cliente por WhatsApp/SMS** | Un proveedor y su canal en `App\Support\AvisosAlCliente`. Hoy salen por `log` o `correo` (entrega en camino, reparación lista). Con eso también se puede recordar la cuota al cliente. |

---

## 3. Diseño

| | Qué | Nota |
|---|---|---|
| 🟡 | **App: lo que queda del rediseño** | La 1.26.0 resolvió Resumen, pestañas, Administración, oscuro y cargas ([CHANGELOG](CHANGELOG.md)). El 2026-10-07 se unificaron buscador, chips de filtro, caja y taller. Falta poco: alguna sección propia en las fichas de compra y de unidad, y la insignia tenue de Ventas en oscuro. Ver `docs/DISENO.md` de la app. |
| 🟡 | **Panel: unificación estructural del HTML** | El SCSS ya está unificado; el HTML no. Las barras de herramientas usan rejillas distintas (3/9, 4/8, 4/5/3), el estado vacío tiene siete implementaciones, el filtro de estado es botonera en unos módulos y `<select>` en otros. Extraer `<x-empty-state>` y `<x-estado-pill>`, una rejilla común, migrar los ocho KPIs del dashboard a `x-stat-card` y pasar las cabeceras de Stock y Kardex a `.crud-hero`. |

---

## 4. Funciones pequeñas que piden en el mostrador

| | Qué | Nota |
|---|---|---|
| 🟡 | **Cambio directo** | Devolver un aparato y llevarse otro en un paso. Hoy son dos: devolver y vender de nuevo. |
| 🟡 | **Recibo del pago de una cuota** | PDF para mandar por WhatsApp, como ya existe el estado de cuenta. |
| 🟡 | **Elegir qué cuota se paga** | Hoy se imputa siempre de la más antigua a la más nueva. Solo si la tienda lo pide: la regla actual es la que evita moras escondidas. |

---

## 5. Calidad del código

| | Qué | Nota |
|---|---|---|
| 🟠 | **Análisis estático en el CI del panel** | Larastan (nivel 5+) y Pint. Hoy el CI solo corre los tests. |
| 🟠 | **App: avisos de `flutter analyze`** | Quedan en `repositorio_api.dart`, caja, compras, POS y ventas. Limpiarlos y hacer que el CI falle con avisos (`--fatal-infos`). |
| 🟡 | **App: archivos demasiado grandes** | `repositorio_api.dart` (1.820 líneas) → un repositorio por dominio. `pantalla_cobro.dart` (1.763) y `pantalla_compra.dart` (1.679) → subwidgets y lógica en notifiers. |
| 🟡 | **App: reporte de errores en producción** | Crashlytics (gratis con Firebase) o Sentry. Hoy un fallo en un teléfono solo se conoce si alguien lo cuenta. |
| 🟡 | **App: reparto del APK** | 79 MB → `--split-per-abi` (~25 MB en arm64). Y un canal de reparto (Play Console, prueba interna) o al menos un enlace de descarga en el aviso de versión. |
| ⏸ | **Redis + Horizon para la cola** | La cola en base de datos alcanza para una tienda. Pasar a Redis solo si los avisos se atrasan. |

---

## 6. Cuando la tienda crezca

Nada de esto hace falta hoy, y adelantarlo costaría más de lo que ahorra.

| | Qué | Nota |
|---|---|---|
| ⏸ | **Segunda sucursal o depósito** | Reforma de fondo: no existe `sucursal_id` ni `almacen_id` en ninguna tabla, y añadirlo toca inventario, ventas, compras y reportes a la vez. Es la decisión más cara de deshacer: pensarla **antes** de abrir el segundo local. |
| ⏸ | **Facturación electrónica** | Requisito legal cuyo alcance fija el Servicio de Impuestos. `compras.numero_factura` guarda la factura del **proveedor**; el sistema no emite nada al cliente. Confirmar el régimen fiscal antes de estimar. |
| ⏸ | **Precios por temporada** | Los precios del día (2026-09-20) cubren buena parte. Revisar si aún hace falta antes de construirlo. |

---

## Por dónde empezar

1. **Esta semana:** los tres procesos del servidor, copias fuera del equipo y
   una restauración de prueba.
2. **HTTPS** y aclarar el `APP_ENV` real. Obliga a recompilar el APK: se hace
   junto con la siguiente versión de la app.
3. **Firebase**: es configuración, y convierte el aviso de autorización en un
   push que llega con la app cerrada.
4. **Repartir la app 1.26.0** (compilar el APK; no necesita backend nuevo) y
   después la deuda de código: avisos de `analyze`, partir los archivos
   grandes, Larastan.
5. **Antes de la sección 6, una decisión, no un desarrollo:** confirmar el
   régimen fiscal. Es lo único que puede obligar a rehacer trabajo ya hecho, y
   averiguarlo es gratis.
