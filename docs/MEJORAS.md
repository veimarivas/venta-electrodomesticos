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

## Pantallas, POS y app móvil (2026-09-13)

Ronda de diseño de pantallas y de funciones del mostrador. No cambia reglas de
negocio existentes: **suma** la autorización de descuentos y la reserva de
unidades, que era lo que faltaba para vender con varias cajas a la vez sin que
dos se peleen por el mismo aparato.

| | Qué | Nota |
|---|---|---|
| ✅ | **Modo oscuro del panel** | El fondo de página (`--marca-fondo`) no se redefinía en oscuro: el cuerpo quedaba claro y las superficies translúcidas se veían blancas. Se corrigió en la marca. |
| ✅ | **Vitrina** | Rediseño con barra de búsqueda fija, secciones con conteo y tarjetas más limpias. |
| ✅ | **Ficha de venta** | El hero no tenía fondo porque el contenedor no entraba en la regla compartida; se rehizo con banda de marca, KPIs y resumen financiero. |
| ✅ | **Reportes y gráficos** | Productos al liderazgo en barras horizontales, dona con el total al centro, barra apilada real por proveedor y colores de marca. |
| ✅ | **Unidades** | Interruptor **En stock / Vendidos** siempre visible y tabla que en móvil pasa a tarjetas (sin scroll lateral). |
| ✅ | **Carrito del POS** | Cada aparato es una tarjeta; el **costo de compra vive tras un ojito** (solo con `reportes.ver_costos`) y el margen se pinta con el ojo encendido. |
| ✅ | **Autorización de descuentos** | Bajar del mínimo obliga a pedir permiso; el administrador aprueba, sugiere o rechaza y el carrito se actualiza solo. |
| ✅ | **Entrega directa o a domicilio** | Por aparato, con dirección/fecha/instalación; la `Entrega` se crea al cobrar. |
| ✅ | **Reserva de unidades** | Al entrar al carrito el aparato pasa a `reservado` con vencimiento; otra caja no lo puede vender. |
| ✅ | **Vistas al día** | Stock, Productos y Unidades muestran las reservadas como «en proceso de venta» y se refrescan solos cada 15 s. |
| ✅ | **App móvil a la par** | Todo lo anterior en el teléfono, más la bandeja de Autorizaciones y la notificación al administrador. |

### Autorización de descuentos

El vendedor rebaja hasta el tope del producto por su cuenta. Bajar más —**sin
llegar por debajo del costo**— abre una solicitud con la foto del momento:
precio de lista, tope, costo y precio pedido. El administrador la resuelve desde
**Ventas → Autorizaciones** (panel) o su pestaña en la app, y puede aprobar el
importe pedido, **sugerir otro monto** o rechazar con un motivo.

La venta se actualiza sola: el POS del vendedor recibe la resolución por
WebSocket (y sondea como respaldo), aplica el monto autorizado o vuelve al
mínimo si se rechazó. Al cobrar, `RegistroDeVenta` no se fía del carrito: busca
en la base una autorización **aprobada, sin usar y que cubra el precio**, y la
consume. Vender por debajo del costo se rechaza siempre.

- Permiso nuevo **`ventas.autorizar_descuento`** (el rol `admin` ya lo tiene por
  `Gate::before`; se puede dar a otro rol desde *Roles y permisos*).
- Tabla **`solicitudes_descuento`**.
- Al pedirla, el administrador recibe una **notificación** (campana del panel y
  avisos de la app) con enlace a la bandeja. El push al teléfono llega cuando
  Firebase esté configurado.

### Reserva de unidades

Agregar al carrito deja el aparato en **`reservado`** («En proceso de venta»)
para que otra caja no lo venda. Al quitarlo vuelve al stock. La reserva
**vence a los 15 minutos**: el POS cierra el carrito abandonado y un barrido
programado (`reservas:liberar`, cada minuto con `schedule:work`) devuelve al
stock las que quedaron colgadas de un carrito que se cerró solo.

`RegistroDeVenta` acepta la reserva **del propio vendedor** y la limpia al
vender; las de los demás siguen bloqueadas.

Las vistas que muestran disponibilidad —**Stock actual**, **Productos** y
**Unidades**— excluyen las reservadas de lo disponible y las pintan como «en
proceso de venta»; se refrescan solas cada 15 s, así que lo que pasa en otra
caja se ve sin recargar.

### API y app móvil

El POS de la API es sin estado: la app arma el carrito y manda todo al cobrar.
Se agregaron `POST /pos/reservar` y `/pos/liberar`, `POST
/pos/solicitudes-descuento` y su estado, `GET /autorizaciones` y `POST
/autorizaciones/{id}/resolver`, el `costo_unitario` (solo con permiso) en el
buscador, y `entrega` por línea en el cobro. La app (repo aparte,
`venta-electrodomesticos-app`) refleja el mismo flujo.

### Lo que queda

- **Push real**: conectar la cuenta de Firebase (`FIREBASE_CREDENTIALS` en el
  servidor y `google-services.json` en la app). El resto del circuito ya avisa
  por la campana y por los avisos de la app.
- **Procesos del servidor**: `queue:work` (avisos de venta), `schedule:work`
  (copias y barrido de reservas) y `reverb:start` (tiempo real) tienen que estar
  corriendo; ver [DESPLIEGUE.md](DESPLIEGUE.md) §4.

---

## Diseño y escaparate público (2026-09-12)

Campaña de diseño sobre las dos caras del producto —el panel y la app— y una
puerta pública al catálogo. No toca ninguna regla de negocio: es cómo se ve y
cómo se llega a lo que ya existía.

| | Qué | Nota |
|---|---|---|
| ✅ | **Escaparate público del catálogo** | `GET /` deja de redirigir a `/dashboard` y muestra la tienda a quien no tiene sesión: recomendados, catálogo por categorías, buscador, filtros por categoría/marca/disponibilidad y ficha de producto con precio. Reutiliza `App\Support\Vitrina` y **nunca expone costos**. Test: `TiendaPublicaTest` (9). |
| ✅ | **Sistema de diseño del panel** | `_sistema.scss` centraliza radios, sombras tintadas, movimiento y estados; `_base.scss` pule los componentes de Velzon (botones, tarjetas, campos, tablas, modales) por encima de la plantilla y por debajo de cada módulo. |
| ✅ | **Vitrina y Stock alineados a la marca** | La Vitrina traía su propia paleta (slate/indigo); ahora usa el azul noche y el oro. Stock pierde el turquesa legado (`#0f766e`) y los acentos verdes del modo oscuro, y sus estados pasan a los tokens del sistema. |
| ✅ | **Login con movimiento de marca** | La banda respira (halo y anillo muy lentos), el contenido entra por capas y el formulario gana micro-interacciones (etiqueta que se enciende, alerta que se desliza, brillo del botón). |
| ✅ | **App Flutter: cabeceras de marca** | La `AppBar` de las pantallas de detalle pasa a azul noche con hilo dorado, y las cinco pestañas principales comparten la banda degradada (`EncabezadoDegradado` con `TabBar`). |

Piezas de fondo que explican el resto:

- **El sistema de diseño vive en dos archivos** (`_sistema.scss` y `_base.scss`)
  en vez de repartirse en treinta. Un módulo nuevo hereda el acabado sin
  escribir una sola regla.
- **La app del teléfono gana una transición propia**: las fichas se abren con un
  desvanecido y las pestañas cambian al instante (`router.dart`), y `Aparecer`
  (en `core/widgets.dart`) da entrada a los bloques respetando «reducir
  movimiento».
- **Avisos se rediseñó** con tarjetas propias, chip por tipo y punto de no leído.

**Lo que queda de esta pieza:** en la app, un buscador y una píldora de filtro
compartidos (hoy hay una decena de copias), unificar las fichas de detalle sobre
`Tarjeta` y rediseñar las pestañas de Administración (usuarios, roles y QR), que
siguen con `ListTile` plano.

---

## Ronda de mejoras (2026-09-12)

Campaña que empezó con el lector de códigos y siguió con lo que estaba a medias
en la app. Todo lo que no dependía de credenciales de terceros quedó hecho.

| | Qué | Nota |
|---|---|---|
| ✅ | **El escáner no leía el código del sistema** | La etiqueta pasó de Code128 a **QR**: la cámara del teléfono lo lee en cualquier orientación y a menos resolución. En el panel y en el modal; la app muestra el QR y el POS acepta QR y Code128 (etiquetas viejas). |
| ✅ | **Devolver un aparato desde el teléfono** | `POST /ventas/{id}/devolver` y botón en la ficha de la venta, con motivo. Las líneas devueltas se tachan. De paso se cableó el botón **Anular**, que estaba muerto. |
| ✅ | **Movimientos de caja en el turno** | Ingresos y retiros: afectan el esperado del arqueo y se listan en el panel y en la app. |
| ✅ | **Comprobantes para el cliente** | Estado de cuenta del crédito y orden de taller en PDF, desde el panel y desde la app (con visor de PDF). |
| ✅ | **Handshake de versión app↔API** | La app avisa si el APK quedó atrás. `VENTAS_APP_MINIMA` se cambia sin tocar código. |
| ✅ | **Buscador del panel: clientes y compras** | Y sugerencias en vivo en el topbar sin recargar. |
| ✅ | **Etiqueta imprimible desde la app** | `GET /unidades/{id}/etiqueta` en PDF (QR en PNG para DomPDF). |
| ✅ | **CI en GitHub Actions** | Los dos repos: suite de Laravel contra MariaDB y `analyze` + tests de Flutter. |
| ✅ | **Tests de flujo de la app** | Cubren devolución/anulación y caja; destaparon y corrigieron un controlador liberado antes de tiempo. |
| ✅ | **Cobro idempotente y cola sin conexión** | Reintentar no duplica; una venta cobrada sin señal se guarda en el teléfono y se envía al volver. |
| ✅ | **Avisos al cliente listos** | Entrega en camino y reparación lista, por un canal configurable (`log` o `correo`); WhatsApp/SMS se añade en un solo sitio. |
| ✅ | **Diagnóstico de push** | `php artisan push:revisar` dice qué falta para que FCM funcione. |

Queda de esta ronda, y solo esto: **conectar las credenciales**. FCM necesita el
`service-account.json` de Firebase y `google-services.json` en la app; los
avisos por WhatsApp o SMS necesitan un proveedor. El código está esperando.

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

El alta de una compra también trabaja desde el teléfono (v1.6.0): la orden se
registra con su proveedor y sus líneas, con el cuadre al centavo del panel; la
edición, la recepción y los pagos completan el módulo desde el mostrador.

---

## Especificaciones en tabla propia (2026-09-06)

Las características del producto vivían en una columna JSON con un fallo de
formato: según por dónde se guardara convivían un objeto `{clave: valor}`, una
lista de pares y un string de más (un `json_encode` en un seeder que el cast
`array` volvía a codificar). Al editar un producto, el formulario mostraba «0»
de característica con todo el JSON pegado en el valor.

Se pasó a la tabla **`producto_especificaciones`** (una fila por característica,
en orden; `valor` null = distintivo sin valor). La migración copió lo que había
tolerando los tres formatos y quitó la columna. El contrato de la API no cambió:
la app sigue mandando y recibiendo una lista de pares `[{clave, valor}]`. Editar
un producto en el panel, en la app y en la ficha de unidades muestra ahora las
características tal como se registraron.

---

## Recepción por tandas, escáner de seriales y pagos (2026-09-06)

Tres piezas que el mostrador pedía a la vez:

| | Qué | Nota |
|---|---|---|
| ✅ | **Recepción parcial** | La mercadería puede llegar por tandas: se marca cuántas unidades llegaron (o los seriales de las que llegaron) y la compra **sigue pendiente** hasta completar el lote. El costo se reparte sobre el lote completo, así la suma sigue cuadrando al centavo al terminar. Una compra con unidades ya generadas no se puede eliminar. |
| ✅ | **Escáner de serial** | En la verificación de mercadería de la app, cada campo de serial tiene su botón de cámara: lee el código del fabricante y lo deja en el campo, sin teclearlo. |
| ✅ | **Pagos a proveedores** | *Compras → Pagos a proveedores* (y una pestaña «Pagos» en la app): el historial de `compra_pagos` con filtros por **hoy / semana / mes / todas** y el total del período. La API `GET /compras/pagos` lo alimenta con su permiso `compras.ver`. |

---

## Indicador de serial en la app (2026-09-06)

| | Qué | Nota |
|---|---|---|
| ✅ | **Etiqueta visual de serial en listado de productos** | Chip "Serial" en azul cuando `tieneSerial` es true, "Sin serial" en gris cuando es false. Permite identificar rápidamente qué productos requieren registro de serial. Versión de la app: 1.8.0+12. |

**Lo que queda:** nada de esta pieza.

## Inventario: etiquetas y estado (2026-09-06)

| | Qué | Nota |
|---|---|---|
| ✅ | **Etiquetas sin precio** | La hoja de etiquetas imprimible ya no muestra el precio de venta: la etiqueta va pegada al aparato en el almacén y el precio no debe ir en la caja. |
| ✅ | **Modal de código de barras** | Botón de código de barras en cada fila de unidades: abre un modal con el SVG Code128 del código interno (el mismo de la etiqueta impresa), sin imprimir. También disponible en la app Flutter (botón *Ver código*). |
| ✅ | **Tabs separados En stock / Vendidos** | En el inventario de unidades, los tabs *En stock* y *Vendido* van primero y separados con un divisor del resto de estados (Reservado, Devuelto, Dañado, En taller, Perdido). En la app Flutter, los chips de estado siguen el mismo orden. |

**Lo que queda:** nada de esta pieza.

## Dashboard: mejoras de diseño y UX (2026-09-07)

| | Qué | Nota |
|---|---|---|
| ✅ | **Imágenes en Bajo mínimo** | El dashboard del panel web y la app Flutter ahora muestran la imagen del producto en la sección de bajo mínimo, facilitando la identificación visual rápida. |
| ✅ | **Reorganización del dashboard** | Últimas ventas y Más vendidos ahora están en la parte superior, debajo de los KPIs, antes de Bajo mínimo. Esto mejora la jerarquía visual: primero la actividad reciente, luego lo que hay que reponer. |
| ✅ | **API de stock bajo con imagen** | El endpoint `GET /api/v1/inventario/stock-bajo` ahora incluye la URL de la imagen del producto para la app móvil. |

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
3. ~~La **fase 2**~~ — hecha el 2026-09-12 (ver la ronda de arriba). Entregas,
   cuotas, reparaciones, compras y devoluciones desde el teléfono.
4. **Lo que queda, y es decidir, no programar** — conectar **Firebase** (push) y
   un proveedor de **WhatsApp/SMS** (avisos al cliente). El resto del código ya
   está hecho. Compruébalo con `php artisan push:revisar`.
5. **Antes de la fase 3, una decisión** — no un desarrollo: confirmar el régimen
   fiscal de la tienda. Es lo único que puede obligar a rehacer trabajo ya
   hecho, y averiguarlo es gratis.

> **Sobre el orden.** La tentación era empezar por lo vistoso. Pero el crédito
> toca la tabla de ventas y las devoluciones también; hacerlos a la vez obligaba
> a rehacer uno de los dos. Se hicieron las devoluciones primero —es más chico—
> y el crédito encima de esa base, que es lo que permitió que devolver un
> aparato de una venta a plazos recorte el plan en vez de romperlo.
