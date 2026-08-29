# Electro Hogar

Sistema de gestión para una tienda de electrodomésticos: catálogo, compras,
inventario aparato por aparato, punto de venta y reportes. Panel web en Laravel
y aplicación móvil en Flutter para el mostrador y el almacén.

## Lo que lo distingue

**El inventario se lleva por unidad física, no por cantidad.** Cada aparato es
un registro con su serial, su código interno, su costo real —prorrateado desde
la factura del proveedor, sin perder centavos— y su historia completa en el
kardex: de qué compra entró, dónde está, en qué venta salió. Es lo que permite
responder «¿dónde está *este* televisor?» en vez de «tenemos cuatro».

## Documentación

| | |
|---|---|
| [MANUAL.md](docs/MANUAL.md) | Cómo se usa, pantalla por pantalla. Para quien atiende |
| [DESPLIEGUE.md](docs/DESPLIEGUE.md) | Poner el sistema en el servidor y mantenerlo vivo |
| [PLAN.md](docs/PLAN.md) | Las decisiones de diseño y por qué se tomaron |
| [MEJORAS.md](docs/MEJORAS.md) | Qué falta, en qué orden y por qué |

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

## Requisitos

PHP 8.3 · MariaDB 10.11 o MySQL 8 · Composer 2 · Node 22
