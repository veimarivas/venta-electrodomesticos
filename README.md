# Electro Hogar

Sistema de gestión para una tienda de electrodomésticos: catálogo, compras,
inventario aparato por aparato, punto de venta, servicio técnico y reportes.
Panel web en Laravel y aplicación móvil en Flutter para el mostrador y el
almacén. La raíz publica además un **escaparate del catálogo** —productos,
precios y disponibilidad—, abierto a quien no tiene sesión.

El **panel** comparte un mismo sistema de diseño en todas sus pantallas —una
banda con degradado, tarjetas de elevación suave, tipografía apretada y el azul
de acción como única señal de «esto se toca»— para que se lea como una sola
casa. La escala vive en tokens (`_marca.scss` y `_sistema.scss`): colores,
radios, sombras, tipografía, movimiento y el degradado de cabecera
(`--grad-marca`) salen de ahí, y una capa de unificación
(`_unificacion.scss`) da una sola forma a las píldoras de estado y a los estados
vacíos. Cambiar la marca es cambiar un token, no treinta archivos. La **app del
teléfono** espeja el mismo sistema en `lib/core/tema.dart` —colores, escala de
espaciado, radios, sombras por brillo y estados—, y sus ocho temas por módulo
apuntan a esos tokens en vez de reescribirlos, así que las dos caras se ven como
el mismo producto. El **servicio técnico** recibe aparatos escaneando la
etiqueta, tanto en la web como desde el teléfono.

## Lo que lo distingue

**El inventario se lleva por unidad física, no por cantidad.** Cada aparato es
un registro con su serial, su código interno, su costo real —prorrateado desde
la factura del proveedor, sin perder centavos— y su historia completa en el
kardex: de qué compra entró, dónde está, en qué venta salió. Es lo que permite
responder «¿dónde está *este* televisor?» en vez de «tenemos cuatro».

## Documentación

| Para… | Documento |
|---|---|
| Usarlo, pantalla por pantalla (quien atiende) | [MANUAL.md](docs/MANUAL.md) |
| Ponerlo en el servidor y mantenerlo vivo | [DESPLIEGUE.md](docs/DESPLIEGUE.md) |
| Levantarlo en local, probarlo y documentar un cambio | [DESARROLLO.md](docs/DESARROLLO.md) |
| Entender cómo está armado y por qué | [ARQUITECTURA.md](docs/ARQUITECTURA.md) |
| Los endpoints de la app del teléfono | [API.md](docs/API.md) |
| Qué se hizo, cuándo y por qué (y qué versión de la app pide qué backend) | [CHANGELOG.md](docs/CHANGELOG.md) |
| Qué falta, en qué orden | [MEJORAS.md](docs/MEJORAS.md) |

La app del teléfono vive en otro repositorio (`../electronica_hogar_app`), con
su propio README y su guía de diseño en `docs/`.

## Puesta en marcha

```bash
composer install && npm install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate --seed
```

```bash
npm run build
```

> **`public/assets/` casi no está en el repositorio.** Lleva la plantilla Velzon
> comprada, así que hay que copiarla a mano. Las imágenes de la marca sí van
> versionadas. El detalle, en [DESPLIEGUE.md](docs/DESPLIEGUE.md).

Para trabajar con datos de ejemplo:

```bash
php artisan db:seed --class=DemoSeeder
```

Genera doce meses de operación pasando por los **mismos servicios** que usa la
aplicación, así que los números cuadran igual que en producción.

## Comprobar que todo sigue en pie

```bash
php artisan test
```

> El **CI** corre esta misma suite en cada push (`.github/workflows/tests.yml`),
> contra MariaDB y compilando los assets.

## Herramientas de operación

```bash
php artisan datos:limpiar
```
Deja la base como recién instalada conservando roles, permisos y la cuenta de
administrador. Para empezar a probar de cero.

```bash
php artisan usuario:acceso correo@ejemplo.com
```
Dice por qué una cuenta no puede entrar, y con `--reset` le devuelve el acceso.

```bash
php artisan push:revisar
```
Dice qué falta para que las notificaciones push lleguen al teléfono (paquete,
credenciales de Firebase y teléfonos registrados).

## Requisitos

PHP 8.3 · MariaDB 10.11 o MySQL 8 · Composer 2 · Node 22
