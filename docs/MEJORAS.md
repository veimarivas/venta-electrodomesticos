# Plan de mejoras

> Qué le falta al sistema, en qué orden y por qué. Para las decisiones ya
> tomadas, ver [PLAN.md](PLAN.md). Para el uso diario, [MANUAL.md](MANUAL.md).
> Para poner el sistema en el servidor, [DESPLIEGUE.md](DESPLIEGUE.md).

El sistema ya hace bien lo difícil: **seguir cada aparato uno a uno**, con su
serial, su costo real prorrateado y su historia completa en el kardex. Lo que le
falta no es más de lo mismo, sino lo que una tienda de electrodomésticos hace
todos los días y hoy sigue anotando aparte.

**Leyenda de estado:** ⬜ pendiente · 🟨 en curso · ✅ hecho

---

## Fase 0 — Antes de construir nada

Ninguna es una función nueva, y por eso van primero: construir encima de un
sistema sin copias de seguridad es apilar trabajo sobre algo que puede
desaparecer.

| | Qué | Esfuerzo |
|---|---|---|
| ⬜ | **Los tres procesos del servidor no están corriendo** | 1 tarde |
| ✅ | **El buscador del panel no busca** | 1 día |
| ✅ | **Una prueba lleva tiempo en rojo** | 2 horas |

### Los tres procesos del servidor

`systemctl` no encuentra `ventas-queue`, `ventas-schedule` ni `ventas-reverb`.
Sin `schedule:work` nadie dispara `backup:run`: **el sistema tiene copias
programadas y ninguna se ejecuta**. Un disco que falle hoy se lleva el
inventario, las ventas y los costos, y nadie se entera hasta que hace falta
restaurar.

De paso caen otras dos: sin `queue:work` no salen los avisos de stock bajo ni
los de venta, y sin `reverb` el panel «en vivo» se queda esperando.

> Es el único punto de esta ruta donde lo que está en juego no es comodidad.

### El buscador del panel ✅

Hecho el 2026-08-29. Busca lo que su propio recuadro promete —producto, serial y
venta— y cada resultado lleva a algo: el producto a su inventario, el aparato
vendido a su venta, la venta a su ficha. Solo aparece lo que el usuario tiene
permiso de ver.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda de esta pieza:** clientes y compras, que hoy no se buscan porque
no tienen a dónde llevar; y sugerencias mientras se escribe, en vez de tener que
enviar el formulario.

### La prueba en rojo ✅

Hecho el 2026-08-29. Comprobaba un texto del encabezado que el rediseño del
listado había cambiado; el filtrado por categoría nunca se rompió. La suite
vuelve a estar entera en verde, que es lo que la hace servir de alarma.

---

## Fase 1 — Lo que la tienda hace y el sistema no ✅

**Completa el 2026-08-30.** Era el grueso del valor, y no eran mejoras del
software existente: eran partes del negocio que vivían fuera, en cuadernos y en
la memoria de quien atiende. Ya no.

Lo que queda de cada pieza está anotado abajo, y casi todo apunta al mismo
sitio: **el teléfono**. Eso es la fase 2.

| | Qué | Esfuerzo |
|---|---|---|
| ✅ | **Devolución y cambio** | 4–5 días |
| ✅ | **Cierre de caja** | 1 semana |
| ✅ | **Venta a crédito y cuotas** | 2–3 semanas |
| ✅ | **Entrega e instalación** | 1–2 semanas |
| ✅ | **Garantía y servicio técnico** | 2 semanas |

### Devolución y cambio ✅

Hecho el 2026-08-29. Desde la ficha de una venta se devuelve un aparato suelto:
vuelve al stock, la venta se queda con el importe de lo que sigue vendido y los
reportes siguen cuadrando sin tocar ninguna consulta. Si se devuelven todos, la
venta queda anulada.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda de esta pieza:** hacerlo también desde la app del teléfono, y el
cambio directo —devolver y llevarse otro— que hoy son dos pasos: devolver y
vender de nuevo.

### Cierre de caja ✅

Hecho el 2026-08-29. *Ventas → Caja*: se abre el turno con su fondo, las ventas
se atan solas y al cerrar se cuenta el cajón. El sistema dice si cuadra, sobra o
falta, y guarda el cierre como una foto que no se mueve aunque después se anule
una venta.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda de esta pieza:** movimientos de caja durante el turno —retirar
para pagar un flete, meter un ingreso— que hoy solo se pueden anotar en las
notas del cierre.

### Venta a crédito y cuotas ✅

Hecho el 2026-08-29. En el punto de venta, *Crédito* es un método de pago más:
se teclea la cuota inicial, en cuántas cuotas se paga el resto y cuándo vence la
primera. *Ventas → Créditos y cuotas* es la cartera —cuánto hay en la calle,
cuánto está vencido, qué vence esta semana— y desde la ficha de cada crédito se
reciben los pagos, que se imputan solos de la cuota más antigua a la más nueva.

Sin interés: la suma de las cuotas es exactamente lo financiado. Al cajón entra
solo la inicial, y las cuotas cobradas en efectivo cuentan en el cierre del
turno en que se recibieron. Cada mañana sale el aviso de lo que vence.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda de esta pieza:** cobrar cuotas desde el teléfono, el estado de
cuenta impreso para dárselo al cliente, y un recordatorio al propio cliente por
WhatsApp o SMS —hoy el aviso es solo para quien cobra—.

### Entrega e instalación ✅

Hecho el 2026-08-29. Desde la ficha de una venta se programa el envío —qué
aparatos, a qué dirección, con qué referencia y qué día— y *Ventas → Entregas*
es el tablero: qué sale hoy, qué está atrasado, qué anda en la calle. Cada
entrega se despacha con su repartidor, se confirma con el nombre de quien
recibió y, si se pactó, se marca la instalación.

Una venta puede partirse en varias entregas, y devolver un aparato o anular la
venta se lleva por delante los envíos que aún no se hicieron.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda de esta pieza:** marcar la entrega **desde el teléfono**, que era
media razón para hacerla —quien reparte lleva el móvil, no el panel—; y avisar
al cliente de que su aparato sale hoy.

### Garantía y servicio técnico ✅

Hecho el 2026-08-30. *Servicio técnico* recibe el aparato buscándolo por su
serial, dice al momento si está en garantía y abre una orden con su número —el
papel con el que vuelve el cliente—. De ahí pasa por diagnóstico, espera de
repuesto si hace falta, y se entrega. El aparato sale del stock mientras está en
el taller y vuelve solo al estado del que salió, con todo en el kardex.

Salió pequeña, como estaba previsto: una tabla nueva y ninguna columna más en
`unidades`.

**De paso apareció un fallo con cara al cliente:** la garantía se contaba desde
que el aparato entró al almacén, no desde que se vendió. Un refrigerador con 12
meses que pasó 8 en el depósito llegaba a casa del comprador con 4 — y esa fecha
recortada era la que se imprimía en su recibo.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda de esta pieza:** el comprobante impreso de la orden para dárselo
al cliente, y avisarle cuando su aparato está listo —hoy hay que llamarlo—.

---

## Fase 2 — Terminar lo que está a medias

Funciones que existen en el panel y no en el teléfono. Cada una es pequeña;
juntas son la diferencia entre «la app sirve para vender» y «sirve para
trabajar».

| | Qué | Esfuerzo |
|---|---|---|
| ✅ | Marcar entregas desde el teléfono | 4 días |
| ✅ | Cobrar cuotas desde el teléfono | 4 días |
| ✅ | Recibir y consultar reparaciones desde el teléfono | 4 días |
| ✅ | Recepcionar compras desde el teléfono | 1 semana |
| ✅ | Anular una venta y ver el recibo desde la app | 3 días |
| ✅ | Editar el propio perfil y la ficha del cliente | 2 días |

### Marcar entregas desde el teléfono ✅

Hecho el 2026-08-30. Era media razón de ser del módulo: quien reparte lleva el
móvil, no el panel. Desde *Ventas → Entregas* en la app se ve la ruta —con el
chip **Lo mío** para ver solo lo suyo—, se despacha, se confirma con el nombre
de quien recibió y se anota un fallo si no se pudo. El serial de cada aparato
va en la tarjeta, que es lo que se comprueba antes de cargar el camión.

Programar sigue siendo del mostrador: hace falta elegir aparatos y teclear una
dirección, y eso se hace con el cliente delante.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda:** tocar el teléfono para marcar en vez de copiarlo —necesita el
paquete `url_launcher`—, y un mapa o enlace a la dirección.

### Cobrar cuotas desde el teléfono ✅

Hecho el 2026-08-30. Dos pantallas colgando de *Ventas*: la cartera —con chips
de vigentes, vencidos y los que vencen esta semana— y el estado de cuenta de
cada crédito, con su plan, sus pagos y el botón de cobrar.

El cobro propone el importe de la cuota que toca y se puede cambiar. Sigue sin
poder elegirse **qué** cuota se paga: el servidor imputa de la más antigua a la
más nueva. Abrir un crédito tampoco se hace desde el móvil — eso ocurre al
cobrar la venta.

El detalle de por qué está así, en [PLAN.md](PLAN.md).

**Lo que queda:** el recibo del pago para mandárselo al cliente por WhatsApp.

### Recepcionar compras desde el teléfono ✅

Hecho el 2026-09-05. Desde la ficha de una compra en estado *Borrador*, el botón
**Recepcionar** genera las unidades físicas del almacén y congela los costos.
Un diálogo de confirmación explica que la operación es irreversible antes de
proceder.

La recepción usa el mismo servicio `RecepcionDeCompra` del panel: prorratea flete
y gastos entre las unidades, genera los códigos internos y registra la entrada
en el kardex. La compra pasa a *Recepcionada* y su costo queda congelado.

**Lo que queda:** nada de esta pieza. La recepción desde el teléfono está
completa.

### Anular una venta y ver el recibo desde la app ✅

Hecho el 2026-09-05. En la ficha de una venta (pantalla de detalle), la barra
superior ahora muestra un **icono de recibo** y un **botón Anular**.

- **Recibo**: descarga el PDF del recibo (inline) y lo abre con el visor del
  teléfono. Funciona tanto para ventas completadas como anuladas: el recibo de
  una venta anulada indica el estado arriba.
- **Anular**: disponible solo para ventas completadas y con permiso
  `ventas.anular`. Se pide un motivo (mínimo 4 caracteres) y se confirma en un
  diálogo. Los aparatos vuelven al stock, se registran los movimientos de kardex
  y la venta queda marcada como anulada. La acción es irreversible.

En el backend, `VentaController@anular` y `VentaController@recibo` añaden las
rutas `POST /ventas/{venta}/anular` y `GET /ventas/{venta}/recibo` bajo el
grupo `auth:sanctum` y los permisos `ventas.anular` y `ventas.ver`
respectivamente. El recibo se devuelve inline (Content-Type: application/pdf),
no como base64, para que el visor del teléfono lo abra directamente.

**Lo que queda:** nada de esta pieza.

### Editar el propio perfil y la ficha del cliente ✅

Hecho el 2026-09-05. La app ahora permite al usuario editar su propio perfil y,
quien tenga permiso `clientes.editar`, editar los datos de un cliente.

**Perfil propio:**
- Nuevo endpoint `PUT /auth/perfil` para actualizar campos de `users` (name,
  email) y de `personas` vinculada (nombres, apellidos, celular, dirección,
  correo, fecha de nacimiento). No requiere permiso especial: cada uno edita lo
  suyo.
- Nuevo endpoint `PUT /auth/password` para cambiar la contraseña (requiere la
  actual + nueva con confirmación).
- Nueva pantalla `PantallaPerfil` en Flutter con botón de acceso desde el
  encabezado del dashboard (icono de perfil). Muestra datos del usuario y su
  persona, con diálogos para editar perfil y cambiar contraseña.
- El `ControladorSesion` ahora tiene `actualizarUsuario()` para reflejar los
  cambios en memoria y en disco sin cerrar sesión.

**Edición de clientes:**
- Nuevo endpoint `POST /clientes/{cliente}` con permiso `clientes.editar`
  (requiere también estar autenticado). Actualiza los datos de la persona
  vinculada al cliente. No requiere `personas.editar`.
- Flutter ya tenía la pantalla de detalle de cliente con formulario de edición
  (`HojaPersona`), que ahora usa el nuevo endpoint.

**Tests:** 15 tests en `PerfilYClientesApiTest` (perfil, contraseña, cliente).

**Lo que queda:** nada de esta pieza. La Fase 2 está completa.

---

## CRUD completo y órdenes de compra desde el teléfono (2026-09-06)

El catálogo ya se administraba desde la app; faltaban tres piezas y entraron las
tres:

| | Qué | Nota |
|---|---|---|
| ✅ | **Editar una compra pendiente** | Endpoint `POST /compras/{compra}` (`compras.editar`) + botón en la ficha de la orden. Mismas reglas que el alta (cuadre al centavo, productos sin repetir) y dos guardas: no se edita una compra **recepcionada** ni una que ya tenga **pagos** — su total empezó a moverse. |
| ✅ | **Editar especificaciones del producto** | El formulario del teléfono pasó de conservarlas a editarlas: una fila por característica, mismo formato «clave: valor» que guarda la base. El backend ya lo soportaba. |
| ✅ | **Papelera desde la app** | Chip «Papelera» en los listados de productos y categorías: lista los archivados (`solo_eliminados`) y los restaura con `POST /…/restaurar` (`productos.editar` / `categorias.editar`). |

El alta de una compra sigue siendo cosa del panel —se hace con las facturas
delante—; la edición y la recepción, en cambio, ya trabajan desde el mostrador.

---

## Indicador de serial en la app (2026-09-06)

| | Qué | Nota |
|---|---|---|
| ✅ | **Etiqueta visual de serial en listado de productos** | Chip "Serial" en azul cuando `tieneSerial` es true, "Sin serial" en gris cuando es false. Permite identificar rápidamente qué productos requieren registro de serial. Versión de la app: 1.8.0+12. |

**Lo que queda:** nada de esta pieza.

## Fase 3 — Cuando la tienda crezca

Nada de esto hace falta hoy, y adelantarlo costaría más de lo que ahorra.

| | Qué | Nota |
|---|---|---|
| ⬜ | Segunda sucursal o depósito | Reforma de fondo |
| ⬜ | Facturación electrónica | Depende del régimen fiscal |
| ⬜ | Precios por temporada | 1 semana |

**Sucursales** es la decisión más cara de deshacer más tarde: no existe
`sucursal_id` ni `almacen_id` en ninguna tabla, y añadirlo toca inventario,
ventas, compras y reportes a la vez. Merece pensarse *antes* de abrir el segundo
local.

**Facturación** no es una mejora de producto sino un requisito legal, y su
alcance lo fija el Servicio de Impuestos. `compras.numero_factura` guarda la
factura del **proveedor**; el sistema no emite ningún documento al cliente.
Conviene confirmar en qué régimen está la tienda antes de estimar nada.

---

## Apuntes técnicos

### Booleanos en multipart/form-data (2026-09-04)

Al enviar un formulario con archivo (multipart) desde la app móvil, los campos
booleanos (`activa`, `activo`) fallaban con 422: *"El campo debe tener un valor
verdadero o falso"*. La causa: Dio serializaba `true`/`false` como strings que
Laravel no aceptaba en el contexto multipart.

**Solución** en `_aplanar()` de `cliente_api.dart`: convertir booleanos a enteros
`1`/`0` en vez de enviarlos como `bool`. Laravel acepta enteros sin problema.
También se eliminó el `Content-Type` global `application/json` y se establece
por petición (`publicar` → JSON, `publicarConArchivo` → multipart) para evitar
conflictos.

---

## Por dónde empezar

1. **Esta semana** — dejar corriendo los tres procesos del servidor y comprobar
   que la copia del día siguiente existe de verdad. Es lo único que queda de la
   fase 0 y no es trabajo de código: sin eso, todo lo demás es opcional.
2. ~~La fase 1 entera~~ — hecha el 2026-08-30. El sistema ya cubre lo que la
   tienda hace todos los días, del cobro a la reparación.
3. **A continuación** — la **fase 2**, que ahora es la que manda. Ha crecido con
   lo que dejó cada pieza nueva: entregas y cuotas desde el teléfono. Son cinco
   trabajos pequeños e independientes, y juntos son la diferencia entre «la app
   sirve para vender» y «sirve para trabajar».
4. **Antes de la fase 3, una decisión** — no un desarrollo: confirmar el régimen
   fiscal de la tienda. Es lo único que puede obligar a rehacer trabajo ya
   hecho, y averiguarlo es gratis.

> **Sobre el orden.** La tentación era empezar por lo vistoso. Pero el crédito
> toca la tabla de ventas y las devoluciones también; hacerlos a la vez obligaba
> a rehacer uno de los dos. Se hicieron las devoluciones primero —es más chico—
> y el crédito encima de esa base, que es lo que permitió que devolver un
> aparato de una venta a plazos recorte el plan en vez de romperlo.
