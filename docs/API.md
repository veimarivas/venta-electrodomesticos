# API v1 para la app

> Base `/api/v1`, autenticación **Sanctum** (Bearer token) y respuestas con API
> Resources. Las rutas van en español, como las tablas.
>
> **Esta lista sale de `php artisan route:list --path=api/v1`** (2026-10-06).
> Si se añade una ruta, se regenera; la columna de permiso es la que exige el
> middleware, y la app no debe asumir otra.

- Límite: **60 peticiones/minuto** por usuario; el login, 5/minuto.
- Versionado en la URL desde el día 1: un cambio incompatible es `/api/v2`, no
  romper `/v1`.
- `GET /version` devuelve `app_minima` (`VENTAS_APP_MINIMA`): la app avisa si
  quedó atrás.
- Edición por `POST /recurso/{id}` y no `PUT`: los formularios llevan archivo
  (multipart) y PHP no lee multipart en `PUT`.
- Costos y ganancias **no se envían** a quien no tiene `reportes.ver_costos`.

## /auth

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| POST | `/auth/login` | correo + contraseña + nombre del dispositivo → token | pública · 5/min |
| POST | `/auth/logout` | revoca el token actual |  |
| PUT | `/auth/password` | cambiar contraseña (requiere actual + nueva con confirmación) |  |
| GET | `/auth/perfil` | datos del usuario y su rol |  |
| PUT | `/auth/perfil` | actualizar nombre, correo y datos personales del usuario autenticado |  |

## /autorizaciones

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/autorizaciones` | bandeja de solicitudes de descuento pendientes y resueltas | `ventas.autorizar_descuento` |
| POST | `/autorizaciones/{solicitud}/resolver` | aprobar, sugerir otro monto o rechazar con motivo | `ventas.autorizar_descuento` |

## /buscar

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/buscar` | buscador global de la app: productos, aparatos, ventas, clientes y compras; cada grupo solo si el usuario tiene su permiso |  |

## /caja

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/caja` | turno abierto con su esperado, ventas y movimientos; `obligatoria` (¿hace falta turno para vender?) y `puede_configurar` | `caja.gestionar o caja.ver` |
| POST | `/caja/abrir` | abre el turno con su fondo inicial | `caja.gestionar` |
| POST | `/caja/obligatoria` | `obligatoria: bool` — exigir o no la caja abierta para vender | `ajustes.editar` |
| POST | `/caja/cerrar` | cierra el turno con lo contado; guarda la foto del cierre | `caja.gestionar` |
| GET | `/caja/cierres` | histórico de cierres con su descuadre (no se recalcula) | `caja.ver` |
| POST | `/caja/movimientos` | ingreso o retiro durante el turno; entra en el esperado | `caja.gestionar` |

## /catalogo

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/catalogo/categorias` | árbol de categorías aplanado, con su nivel y conteos | `productos.ver` |
| POST | `/catalogo/categorias` | alta y edición (el `{id}` es la edición; POST y no PUT por el multipart) | `categorias.crear` |
| POST | `/catalogo/categorias/{categoria}` | alta y edición (el `{id}` es la edición; POST y no PUT por el multipart) | `categorias.editar` |
| DELETE | `/catalogo/categorias/{categoria}` | baja; se niega si tiene subcategorías | `categorias.eliminar` |
| POST | `/catalogo/categorias/{categoria}/restaurar` | la papelera del árbol (`categorias.editar`) | `categorias.editar` |
| POST | `/catalogo/importar` | carga masiva del catálogo desde un `.xlsx` (multipart) | `productos.crear o categorias.crear` |
| GET | `/catalogo/marcas` | marcas con sus productos y sus unidades en stock | `productos.ver` |
| POST | `/catalogo/marcas` | alta y edición, con logo opcional | `marcas.crear` |
| POST | `/catalogo/marcas/{marca}` | alta y edición, con logo opcional | `marcas.editar` |
| DELETE | `/catalogo/marcas/{marca}` | baja **real** (no hay papelera); se niega si tiene productos | `marcas.eliminar` |
| GET | `/catalogo/plantilla` | plantilla `.xlsx` de carga masiva | `productos.crear o categorias.crear` |
| GET | `/catalogo/productos` | listado paginado | `productos.ver` |
| POST | `/catalogo/productos` | alta y edición, con foto opcional | `productos.crear` |
| GET | `/catalogo/productos/{producto}` | ficha con especificaciones y unidades disponibles | `productos.ver` |
| POST | `/catalogo/productos/{producto}` | alta y edición, con foto opcional | `productos.editar` |
| DELETE | `/catalogo/productos/{producto}` | baja lógica; las unidades y el histórico se conservan | `productos.eliminar` |
| POST | `/catalogo/productos/{producto}/restaurar` | la papelera: devuelve el producto al catálogo (`productos.editar`) | `productos.editar` |
| GET | `/catalogo/vitrina` | recomendados y categorías, la misma fuente que la Vitrina del panel | `productos.ver` |

## /clientes

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/clientes` | listado paginado con el resumen de compras | `clientes.ver` |
| POST | `/clientes` | alta de cliente desde cero, cuando no aparece por ningún lado | `clientes.crear` |
| POST | `/clientes/desde-persona` | le abre la ficha de cliente con los datos que ya tiene (restaura la archivada si la hubo) | `clientes.crear` |
| GET | `/clientes/{cliente}` | ficha con sus últimas compras | `clientes.ver` |
| DELETE | `/clientes/{cliente}` | archiva la ficha; su historial se conserva | `clientes.eliminar` |
| POST | `/clientes/{cliente}` | editar datos personales del cliente (`clientes.editar`) | `clientes.editar` |
| POST | `/clientes/{cliente}/restaurar` | la devuelve al listado con su código | `clientes.editar` |

## /compras

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/compras` | listado paginado | `compras.ver` |
| POST | `/compras` | registra una orden de compra con sus líneas (cuadre al centavo) | `compras.crear` |
| GET | `/compras/pagos` | historial de pagos a proveedores con filtro de período y total | `compras.ver` |
| GET | `/compras/verificadores` | usuarios activos que pueden verificar compras (`id`, `nombre`) | `compras.editar` |
| POST | `/compras/{compra}/asignar` | `verificador_id` (o null para quitar): encarga la verificación y avisa al vendedor | `compras.editar` |
| GET | `/compras/{compra}` | ficha con el desglose y las líneas con su costo real | `compras.ver` |
| POST | `/compras/{compra}` | edita una compra **pendiente sin pagos**: proveedor, fecha, factura, total y líneas (`compras.editar`); mismas reglas que el alta (cuadre al centavo) | `compras.editar` |
| DELETE | `/compras/{compra}` | elimina una compra pendiente sin unidades generadas | `compras.eliminar` |
| GET | `/compras/{compra}/etiquetas` | PDF con las etiquetas del lote completo | `unidades.ver` |
| GET | `/compras/{compra}/pagos` | pagos de una compra y su saldo | `compras.ver` |
| POST | `/compras/{compra}/pagos` | anota un pago al proveedor, con boucher opcional | `compras.crear` |
| DELETE | `/compras/{compra}/pagos/{pago}` | borra un pago anotado por error | `compras.crear` |
| POST | `/compras/{compra}/recepcionar` | recepciona la compra: genera unidades y congela costos (`compras.crear`) | `compras.crear` |
| POST | `/compras/{compra}/seriales` | seriales de varias unidades de la compra a la vez | `unidades.editar` |
| GET | `/compras/{compra}/unidades` | aparatos que entraron con esa compra | `compras.ver` |

## /compras-por-verificar

Lo que ve el vendedor al que se le asignó una compra: **sin costos ni pagos**.
Solo las suyas (el administrador, con `compras.crear`, puede abrir cualquiera).

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/compras-por-verificar` | `pendientes` y las últimas `verificadas` del usuario | `compras.verificar o compras.crear` |
| GET | `/compras-por-verificar/{compra}` | ficha: proveedor, factura y `lineas` (`id`, `producto`, `tiene_serial`, `cantidad`, `recibidas`, `faltan`) | `compras.verificar o compras.crear` · 403 si no es suya |
| POST | `/compras-por-verificar/{compra}/recepcionar` | `lineas[]`: `{linea_id, seriales[]}` o `{linea_id, cantidad_verificada}`; se puede por tandas | `compras.verificar o compras.crear` |

## /creditos

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/creditos` | cartera: vigentes, vencidos y los que vencen esta semana | `creditos.ver` |
| GET | `/creditos/{credito}` | estado de cuenta: plan de cuotas y pagos | `creditos.ver` |
| POST | `/creditos/{credito}/cobrar` | cobra un importe; se imputa de la cuota más antigua a la más nueva | `creditos.cobrar` |
| GET | `/creditos/{credito}/estado-cuenta` | estado de cuenta en PDF para el cliente | `creditos.ver` |

## /dashboard

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/dashboard/grafica` | serie temporal para la gráfica | `reportes.ver` |
| GET | `/dashboard/inventario` | qué hay en la estantería **ahora** (sin rango; costo solo con `ver_costos`) | `reportes.ver` |
| GET | `/dashboard/por-metodo-pago` | reparto del ingreso, con la etiqueta ya traducida | `reportes.ver` |
| GET | `/dashboard/por-vendedor` | cuánto vendió cada uno (la ganancia solo con `ver_costos`) | `reportes.ver` |
| GET | `/dashboard/resumen` | total vendido, nº de ventas, ganancia, ticket promedio y comparativo con el periodo anterior | `reportes.ver` |
| GET | `/dashboard/top-productos` | ranking de los más vendidos | `reportes.ver` |

## /dispositivos

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/dispositivos` | teléfonos registrados del usuario para push |  |
| POST | `/dispositivos` | registra el token FCM del teléfono |  |
| DELETE | `/dispositivos/{token}` | da de baja el dispositivo |  |

## /entregas

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/entregas` | tablero de entregas (hoy, atrasadas, en camino); `esta_atrasada` la calcula el servidor | `entregas.ver` |
| GET | `/entregas/repartidores` | personal que puede llevar una entrega | `entregas.ver` |
| GET | `/entregas/{entrega}` | ficha de la entrega con sus aparatos y seriales | `entregas.ver` |
| POST | `/entregas/{entrega}/confirmar` | entregada, con el nombre de quien recibió | `entregas.gestionar` |
| POST | `/entregas/{entrega}/despachar` | sale a reparto con su repartidor | `entregas.gestionar` |
| POST | `/entregas/{entrega}/fallar` | no se pudo entregar, con motivo | `entregas.gestionar` |
| POST | `/entregas/{entrega}/reprogramar` | nueva fecha (o «cuando se pueda») | `entregas.gestionar` |

## /gastos

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/gastos?fecha=` | gastos del día; `meta`: `total`, `por_metodo`, `categorias`, `metodos`, `personas`, `caja_abierta` | `gastos.ver` |
| POST | `/gastos` | alta (multipart, `comprobante` opcional): concepto, categoría, monto, `metodo_pago` (qr, efectivo, transferencia), `beneficiario_id`, `de_caja` 1/0 | `gastos.crear` |
| POST | `/gastos/{gasto}` | edición | `gastos.editar` |
| DELETE | `/gastos/{gasto}` | archiva (borrado lógico) | `gastos.eliminar` |

## /inventario

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/inventario/stock-bajo` | productos por debajo del mínimo | `inventario.ver` |

## /notificaciones

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/notificaciones` | historial de avisos |  |
| POST | `/notificaciones/leidas` | marca todos los avisos como leídos |  |
| POST | `/notificaciones/{id}/leida` | marca un aviso como leído |  |

## /personal

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/personal/cargos` | cargos con cuánta gente los ocupa (vigentes y bajas) | `cargos.ver` |
| POST | `/personal/cargos` | alta y edición | `cargos.crear` |
| POST | `/personal/cargos/{cargo}` | alta y edición | `cargos.editar` |
| DELETE | `/personal/cargos/{cargo}` | baja **real**; se niega si alguna vez tuvo trabajadores | `cargos.eliminar` |
| GET | `/personal/trabajadores` | listado paginado | `trabajadores.ver` |
| POST | `/personal/trabajadores` | alta (persona nueva o existente) y edición de la ficha laboral | `trabajadores.crear` |
| GET | `/personal/trabajadores/{trabajador}` | ficha con su cuenta de acceso y lo que vendió | `trabajadores.ver` |
| POST | `/personal/trabajadores/{trabajador}` | alta (persona nueva o existente) y edición de la ficha laboral | `trabajadores.editar` |
| POST | `/personal/trabajadores/{trabajador}/baja` | cierra o reabre la ficha, y con ella la cuenta de acceso | `trabajadores.eliminar` |
| POST | `/personal/trabajadores/{trabajador}/reactivar` | reabre la ficha laboral y su cuenta | `trabajadores.editar` |

## /personas

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/personas/sin-ficha` | segundo peldaño del buscador: personas ya registradas que aún no son clientes | `clientes.crear` |
| POST | `/personas/{persona}` | datos personales; **el único sitio donde se editan** | `personas.editar` |

## /pos

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/pos/buscar` | aparatos vendibles; marca la coincidencia exacta del escáner. Con `escaneado=1`, si no hay nada vendible devuelve `meta.diagnostico` explicando si el aparato ya se vendió (con su venta) o si el código no existe. Cada aparato trae `tiene_serial`; uno **sin serial** sale una sola vez por producto con `disponibles` | `ventas.crear` |
| POST | `/pos/cobrar` | registra la venta (multipart: lleva la foto del comprobante). Sin techo: el precio puede pasar de la lista (se registra lo cobrado, sin descuento); la referencia es el precio del día | `ventas.crear` |
| POST | `/pos/liberar` | devuelve al stock una unidad reservada del carrito | `ventas.crear` |
| GET | `/pos/qrs` | QR de cobro **vigentes**, con su imagen (lo que el mostrador puede usar) | `ventas.crear` |
| POST | `/pos/reservar` | aparta una unidad 20 minutos para que otra caja no la venda | `ventas.crear` |
| POST | `/pos/reservar-cantidad` | venta por cantidad de un producto **sin serial**: `producto_id`, `cantidad`, `excluir` (las del carrito) → aparta las más antiguas; `meta.mensaje` si no había tantas. 422 si el producto lleva serial | `ventas.crear` |
| POST | `/pos/solicitudes-descuento` | pide autorización para bajar del mínimo (nunca del costo) | `ventas.crear` |
| GET | `/pos/solicitudes-descuento/{solicitud}` | estado de la solicitud (el carrito la sondea) | `ventas.crear` |

## /precios-del-dia

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/precios-del-dia` | productos con stock, precio de ayer y el de hoy si ya está; `sugerencia` (o `null`) cuando llegó una compra con otro costo: `tipo` sube/baja, `precio_sugerido`, `costo_anterior`, `costo_nuevo`, `variacion_costo` (%), `compra_codigo`, `recibida_en`. `meta.sugerencias` las cuenta. No se aplica sola | `caja.gestionar o productos.editar` |
| POST | `/precios-del-dia` | guarda los precios de la jornada (nunca en el costo o por debajo) | `caja.gestionar o productos.editar` |
| GET | `/precios-del-dia/estado` | ¿se puede cobrar hoy? (precios listos) | `ventas.crear` |

## /proveedores

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/proveedores` | listado paginado con lo invertido en cada uno | `proveedores.ver` |
| POST | `/proveedores` | alta y edición | `proveedores.crear` |
| GET | `/proveedores/{proveedor}` | ficha con sus últimas órdenes | `proveedores.ver` |
| POST | `/proveedores/{proveedor}` | alta y edición | `proveedores.editar` |
| DELETE | `/proveedores/{proveedor}` | baja lógica; se niega si tiene compras registradas | `proveedores.eliminar` |

## /qrs-cobro

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/qrs-cobro` | **todos** los QR, con `vigente` ya resuelto | `qrs_cobro.ver` |
| POST | `/qrs-cobro` | alta y edición; la imagen solo es obligatoria al crear | `qrs_cobro.crear` |
| POST | `/qrs-cobro/{qr}` | alta y edición; la imagen solo es obligatoria al crear | `qrs_cobro.editar` |
| DELETE | `/qrs-cobro/{qr}` | archiva; su imagen **no** se borra | `qrs_cobro.eliminar` |

## /reparaciones

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/reparaciones` | listado paginado con filtros (abiertas, atrasadas, en_taller, listas, cerradas, todas) | `reparaciones.ver` |
| POST | `/reparaciones` | recibir unidad en el taller y abrir orden (`reparaciones.recibir`) | `reparaciones.recibir` |
| GET | `/reparaciones/buscar-unidad` | buscar unidad por serial o código interno | `reparaciones.ver` |
| GET | `/reparaciones/{reparacion}` | ficha completa con historial del kardex | `reparaciones.ver` |
| GET | `/reparaciones/{reparacion}/comprobante` | orden de servicio en PDF para el cliente | `reparaciones.ver` |
| POST | `/reparaciones/{reparacion}/diagnosticar` | anotar diagnóstico y costo estimado (`reparaciones.atender`) | `reparaciones.atender` |
| POST | `/reparaciones/{reparacion}/entregar` | entregar al cliente con nombre de quien recibe (`reparaciones.recibir`) | `reparaciones.recibir` |
| POST | `/reparaciones/{reparacion}/lista` | marcar como lista para entrega (`reparaciones.atender`) | `reparaciones.atender` |

## /reportes

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/reportes/compras/{compra}/rentabilidad` | rentabilidad de una compra | `reportes.ver` |
| GET | `/reportes/proveedores` | rentabilidad por proveedor | `reportes.ver` |
| GET | `/reportes/resumen-diario?fecha=` | ingresos y egresos del día por concepto y medio, neto y efectivo del día | `reportes.seguimiento` |
| GET | `/reportes/vendedores?desde=&hasta=&vendedor_id=` | por vendedor: ventas, unidades, descuento, sobreprecio, detalle por producto y desvíos de la lista | `reportes.seguimiento` |

## /roles

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/roles` | roles, matriz por módulo y lo que tiene marcado | `roles.ver` |
| POST | `/roles` | alta, nombre y sincronización de permisos | `roles.crear` |
| GET | `/roles/permisos` | roles, matriz por módulo y lo que tiene marcado | `roles.ver` |
| POST | `/roles/{rol}` | alta, nombre y sincronización de permisos | `roles.editar` |
| DELETE | `/roles/{rol}` | baja; se niega si alguien lo tiene asignado | `roles.eliminar` |
| GET | `/roles/{rol}/permisos` | roles, matriz por módulo y lo que tiene marcado | `roles.ver` |
| POST | `/roles/{rol}/permisos` | alta, nombre y sincronización de permisos | `roles.editar` |

## /unidades

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/unidades` | inventario por unidad física, con filtros por estado | `unidades.ver` |
| POST | `/unidades` | alta manual de una unidad (precio mayor que el costo) | `unidades.editar` |
| GET | `/unidades/{unidad}` | ficha del aparato con su kardex | `unidades.ver` |
| POST | `/unidades/{unidad}` | edita precio y datos de la unidad (no si ya se vendió) | `unidades.editar` |
| GET | `/unidades/{unidad}/etiqueta` | etiqueta en PDF con su QR | `unidades.ver` |
| POST | `/unidades/{unidad}/serial` | registra el serial del fabricante leído con la cámara (`unidades.editar`) | `unidades.editar` |

## /usuarios

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/usuarios` | cuentas de acceso con sus roles | `usuarios.ver` |
| POST | `/usuarios` | alta y edición; contraseña vacía = no cambiarla | `usuarios.crear` |
| GET | `/usuarios/personas` | personas que aún no tienen cuenta | `usuarios.ver` |
| POST | `/usuarios/{usuario}` | alta y edición; contraseña vacía = no cambiarla | `usuarios.editar` |
| DELETE | `/usuarios/{usuario}` | elimina la cuenta; la persona se conserva | `usuarios.eliminar` |
| POST | `/usuarios/{usuario}/estado` | activa o desactiva la cuenta | `usuarios.editar` |

## /ventas

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/ventas` | listado paginado | `ventas.ver` |
| GET | `/ventas/{venta}` | detalle con unidades, seriales, costos y ganancia | `ventas.ver` |
| POST | `/ventas/{venta}/anular` | anula la venta con motivo (`ventas.anular`); devuelve unidades al stock | `ventas.anular` |
| POST | `/ventas/{venta}/devolver` | devuelve un aparato de la venta, con motivo; vuelve al stock | `ventas.anular` |
| GET | `/ventas/{venta}/entregables` | aparatos de la venta que faltan por entregar y los ya repartidos (`ventas.ver`) | `ventas.ver` |
| POST | `/ventas/{venta}/entregas` | programa una entrega para unas líneas de la venta (`ventas.ver` + `entregas.crear`) | `ventas.ver, entregas.crear` |
| GET | `/ventas/{venta}/recibo` | recibo en PDF inline (`ventas.ver`) | `ventas.ver` |

## /version

| Método | Ruta | Qué hace | Permiso / notas |
|---|---|---|---|
| GET | `/version` | `app_minima` que exige el servidor | pública |
