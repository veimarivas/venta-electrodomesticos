# Desarrollo

> Cómo levantar el proyecto en local, dónde está cada cosa, cómo se prueba y
> dónde se anota cada cambio. Para el servidor de la tienda, ver
> [DESPLIEGUE.md](DESPLIEGUE.md); para el porqué del diseño,
> [ARQUITECTURA.md](ARQUITECTURA.md).

---

## 1. Levantar el proyecto

Requisitos: PHP 8.3, MariaDB 10.11 (la de XAMPP) o MySQL 8, Composer 2, Node 22.

```bash
composer install && npm install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate --seed
```

Y en dos terminales:

```bash
php artisan serve
```

```bash
npm run dev
```

Para el **dashboard en vivo** y el refresco instantáneo de inventario hace falta
además el servidor de WebSockets. Sin él todo funciona igual: las vistas sondean
cada 10–20 s.

```bash
php artisan reverb:start
```

> **`public/assets/` casi no está en el repositorio.** Lleva la plantilla Velzon
> comprada y hay que copiarla a mano (ver §6 y [DESPLIEGUE.md](DESPLIEGUE.md)).

> **MariaDB, no MySQL.** El XAMPP trae MariaDB. Laravel 13 la soporta
> oficialmente; el driver del `.env` es `mariadb` (no `mysql`) para que las
> migraciones generen el SQL correcto, y se evitan columnas `virtual generated`
> sobre JSON.

### Datos de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| admin | `admin@electronicahogar.test` | `password` |
| vendedor | `vendedor@electronicahogar.test` | `password` |

> **Solo para local.** En el servidor hay que cambiar la del admin y borrar la
> del vendedor antes de abrir la tienda ([DESPLIEGUE.md §6](DESPLIEGUE.md)).

Para una base con historia (reportes con datos, gráficas con forma):

```bash
php artisan db:seed --class=DemoSeeder
```

Genera doce meses de operación pasando por los mismos servicios que la
aplicación. Se niega a correr si ya hay ventas o en `APP_ENV=production`.

---

## 2. Dónde está cada cosa

```
app/
├── Http/Controllers/        panel web (Blade + Livewire)
│   └── Api/V1/              API de la app del teléfono (ver API.md)
├── Livewire/                pantallas interactivas del panel (CRUD, POS, reportes)
├── Support/                 LA LÓGICA DE NEGOCIO: RegistroDeVenta, RecepcionDeCompra,
│                            ProrrateoDeGastos, PreciosDelDia, ArqueoDeCaja,
│                            CobroDeCuota, AutorizacionDeDescuento, Vitrina…
├── Events/                  VentaRegistrada, InventarioActualizado, SolicitudDeDescuento*
├── Notifications/           avisos (base de datos + FCM cuando hay credenciales)
├── Console/Commands/        datos:limpiar, usuario:acceso, push:revisar,
│                            cuotas:avisar, reservas:liberar
└── Http/Middleware/         EnsureUserIsActive, CabecerasDeSeguridad,
                             LiberarReservasVencidas
config/
├── menu.php                 estructura del sidebar (se edita aquí, no en el Blade)
├── velzon.php               apariencia de la plantilla
├── backup.php               qué se respalda y cuánto se conserva
└── avisos.php               canal de los avisos al cliente (log | correo)
routes/
├── web.php · api.php        panel y API v1
├── channels.php             canales privados de Reverb
└── console.php              tareas programadas (necesitan schedule:work)
resources/
├── scss/components/         _marca y _sistema (tokens), _base, _unificacion y un
│                            archivo por módulo
├── js/                      app.js, avisos.js (campana con sonido y sondeo)
└── views/                   backend/, livewire/, components/ (x-page-title,
                             x-stat-card, x-sort-icon…)
tests/                       Feature/ y Unit/ (PHPUnit)
docs/                        esta documentación
```

**Regla de la casa:** los controladores y componentes Livewire **no** tienen
reglas de negocio; llaman a una clase de `app/Support`. Por eso el panel y la API
venden, cobran y recepcionan exactamente igual: es el mismo código.

---

## 3. Pruebas

```bash
php artisan test
```

Se usa **PHPUnit** (lo que trae Laravel 13). Unas 970 pruebas en 69 archivos.
El **CI** (`.github/workflows/tests.yml`) corre la suite en cada push contra
MariaDB y compilando los assets.

Lo que las pruebas fijan, por grupos:

- **Dinero:** el prorrateo del landed cost suma exactamente el total de la compra
  (300 casos aleatorios); el costo se congela al vender; los reportes descartan
  las ventas anuladas y no dividen por cero.
- **Inventario:** la recepción es atómica; toda unidad tiene su entrada en el
  kardex; el kardex es de solo escritura; un ajuste sin motivo se rechaza; no se
  vende dos veces la misma unidad; la reserva de 20 minutos se libera sola.
- **Ventas:** venta atómica (sin cabeceras huérfanas); anular y devolver
  devuelven al stock; el cobro es idempotente; sin caja abierta ni precios del
  día no se cobra; la autorización de descuento se consume una sola vez.
- **Seguridad:** ninguna pantalla sin sesión; una cuenta desactivada pierde la
  sesión abierta; los costos no viajan por API a quien no los puede ver;
  cabeceras de seguridad; el `.env.example` no lleva secretos.
- **Tiempo real:** la venta y el aviso se registran aunque Reverb esté caído.

Pendiente de probar: el envío real de push FCM (requiere credenciales).

> Un test en rojo es una alarma, no ruido: si falla por un texto que cambió a
> propósito, se actualiza el test en el mismo commit.

---

## 4. Cómo se documenta un cambio

| Documento | Qué va | Cuándo se toca |
|---|---|---|
| [CHANGELOG.md](CHANGELOG.md) | Qué cambió, por qué y cómo se comprobó, con fecha y versión de la app | **En cada cambio**, arriba del todo |
| [MEJORAS.md](MEJORAS.md) | Solo lo pendiente, priorizado | Al detectar algo pendiente y al terminarlo (se borra de aquí y pasa al CHANGELOG) |
| [ARQUITECTURA.md](ARQUITECTURA.md) | Decisiones de fondo y modelo de datos | Al añadir una tabla o cambiar una decisión |
| [API.md](API.md) | Endpoints de `/api/v1` | Al añadir o cambiar una ruta de la API |
| [MANUAL.md](MANUAL.md) | Cómo se usa, para quien atiende | Cuando cambia lo que ve el usuario |
| [DESPLIEGUE.md](DESPLIEGUE.md) | Servidor, procesos, copias | Cuando cambia un proceso, una variable o un paso de despliegue |

Así cada cosa vive en un solo sitio: lo hecho no se repite en MEJORAS, y
MEJORAS no se convierte en otro historial.

La app del teléfono tiene su propia documentación en su repositorio
(`../electronica_hogar_app/README.md` y `docs/`), pero **su historial se anota
aquí**, en el CHANGELOG, porque casi todo cambio de la app toca la API.

---

## 5. Commits

Mensajes en español con prefijo de tipo: `feat(modulo):`, `fix(modulo):`,
`style(...)`, `docs:`, `chore(...)`. El cuerpo explica el porqué. Si el cambio
necesita una versión nueva de la app, el commit de la app lleva la versión en el
título (`… (1.21.0)`).

---

## 6. La plantilla Velzon

### Ajustes hechos sobre la plantilla

- **Menú desde configuración.** El sidebar sale de `config/menu.php` a través de `App\Support\MenuBuilder`: resuelve rutas, oculta lo que el usuario no tiene permitido, marca la rama activa y descarta los títulos de sección que se quedan sin ítems. Los módulos aún no implementados apuntan a `#` en lugar de reventar con `RouteNotFoundException`.
- **`assets/js/plugins.js` no se carga.** Usa `document.writeln()` con rutas relativas (`assets/libs/…`) que se rompen en cualquier ruta anidada como `/inventario/items`, y descarga toastify desde un CDN externo. Sus librerías las provee Vite.
- **Los date pickers usan `data-datepicker`, no `data-provider="flatpickr"`.** El `app.js` de Velzon recorre todo elemento con `data-provider` y lee `data-date-format` sin comprobar que exista: lanzaba un `TypeError` que cortaba el resto de su inicialización (entre otras cosas, el botón *back to top* dejaba de funcionar).
- **El panel de personalización se incluye siempre**, aunque su botón sea condicional (`VELZON_CUSTOMIZER=true`): `app.js` enlaza listeners a sus controles sin comprobar si existen.
- **Sin Tailwind.** Venía en el esqueleto de Laravel 13; su *preflight* pisa los estilos de Bootstrap. Vite compila SCSS propio que se carga encima de la plantilla.
- **`bg-opacity-15` no existe en Bootstrap** (solo `10/25/50/75/100`). El fondo translúcido se declara en SCSS (`rgba(255,255,255,.16)`).

### Faltantes de la copia de la plantilla

Archivos que Velzon referencia en su CSS y no están en lo que se copió:

- `assets/fonts/hkgrotesk-*.woff` — la fuente secundaria. Provocaba un 404 por página; se apunta `--vz-font-family-secondary` a Poppins en `resources/scss/app.scss` (revertible: si copias los archivos, borra esa línea).
- `assets/images/cover-pattern.png` — trama decorativa de la pantalla de login.
- `assets/images/demo/*.png` — miniaturas de temas del personalizador; se reemplazaron por bloques vacíos.

Si tienes el paquete original de Velzon a mano, copiar esos archivos a `public/assets/` los restaura sin más cambios.
