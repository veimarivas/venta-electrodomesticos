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

## Fase 1 — Lo que la tienda hace y el sistema no

El grueso del valor. No son mejoras del software existente: son partes del
negocio que hoy viven fuera, en cuadernos y en la memoria de quien atiende.

| | Qué | Esfuerzo |
|---|---|---|
| ✅ | **Devolución y cambio** | 4–5 días |
| ✅ | **Cierre de caja** | 1 semana |
| ✅ | **Venta a crédito y cuotas** | 2–3 semanas |
| ⬜ | **Entrega e instalación** | 1–2 semanas |
| ⬜ | **Garantía y servicio técnico** | 2 semanas |

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

### Entrega e instalación

**Hoy:** ninguna tabla ni modelo menciona entrega, despacho ni dirección de
envío. La venta termina al cobrar.

Un refrigerador no sale de la tienda en la mano del cliente. Entre cobrar y
entregar hay días, una dirección, alguien que lo lleva y un cliente que llama
preguntando. Con la app ya en el mostrador, quien reparte podría marcar la
entrega desde el camión.

### Garantía y servicio técnico

**Hoy:** `productos.meses_garantia` calcula una fecha (`garantia_hasta`) y ahí
termina.

El sistema sabe decir si un aparato está en garantía, pero no qué hacer después.
Cuando el cliente vuelve con una lavadora que no enciende, empieza un rastro en
papel.

La pieza que falta es pequeña porque **el kardex ya existe**: una reparación es
otro tipo de movimiento sobre una unidad ya identificada por serial.

---

## Fase 2 — Terminar lo que está a medias

Funciones que existen en el panel y no en el teléfono. Cada una es pequeña;
juntas son la diferencia entre «la app sirve para vender» y «sirve para
trabajar».

| | Qué | Esfuerzo |
|---|---|---|
| ⬜ | Cobrar cuotas desde el teléfono | 4 días |
| ⬜ | Recepcionar compras desde el teléfono | 1 semana |
| ⬜ | Anular una venta y ver el recibo desde la app | 3 días |
| ⬜ | Editar el propio perfil y la ficha del cliente | 2 días |

---

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

## Por dónde empezar

1. **Esta semana** — dejar corriendo los tres procesos del servidor y comprobar
   que la copia del día siguiente existe de verdad. Es lo único que queda de la
   fase 0 y no es trabajo de código: sin eso, todo lo demás es opcional.
2. ~~Devolución, cierre de caja y venta a crédito~~ — hechas. Con eso, la fase 1
   se queda en lo que pasa **después** de cobrar.
3. **A continuación** — entrega e instalación. Es la siguiente que la tienda hace
   todos los días y el sistema no: entre cobrar un refrigerador y dejarlo en la
   casa del cliente hay días, una dirección y alguien que llama preguntando.
4. **En paralelo** — las piezas de la fase 2. Son pequeñas, independientes y no
   bloquean nada. Ahora hay una más: cobrar cuotas desde el teléfono.

> **Sobre el orden.** La tentación era empezar por lo vistoso. Pero el crédito
> toca la tabla de ventas y las devoluciones también; hacerlos a la vez obligaba
> a rehacer uno de los dos. Se hicieron las devoluciones primero —es más chico—
> y el crédito encima de esa base, que es lo que permitió que devolver un
> aparato de una venta a plazos recorte el plan en vez de romperlo.
