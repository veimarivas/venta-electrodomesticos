# Manual de uso — Electrónica del Hogar

> Para quien trabaja con el sistema todos los días. Instalación y servidor en
> [DESPLIEGUE.md](DESPLIEGUE.md); decisiones técnicas en [PLAN.md](PLAN.md).

---

## 1. La idea de fondo, en un minuto

**Cada aparato de la tienda es un registro.** No se lleva «12 televisores»: se
llevan doce televisores, cada uno con su código interno, su serial, lo que
costó de verdad y a quién se le vendió.

Eso es lo que hace posible todo lo demás:

- Saber la **ganancia real** de una venta, no una estimación.
- Seguir un aparato de la factura del proveedor hasta el cliente.
- Atender una garantía sabiendo cuándo entró, cuánto costó y qué compra lo trajo.

Y explica dos cosas que a primera vista sorprenden: las compras generan
**muchas** filas de golpe, y las ventas **no se borran**.

---

## 2. Entrar

| | |
|---|---|
| Dirección | la que dé el administrador |
| Usuario | tu **nombre de usuario** (`jperezlopez`) **o** tu correo |
| Contraseña | tu carnet, la primera vez |

El menú lateral solo muestra lo que tu rol permite. Si echas en falta una
sección, es permisos, no un fallo.

**Cámbiala en cuanto entres**, desde *Perfil* (arriba a la derecha). La
contraseña inicial es tu carnet y la conoce quien te dio de alta.

> ¿«Cuenta bloqueada»? Tu cuenta está desactivada — normalmente porque se
> registró tu baja. Habla con el administrador.

---

## 3. El día a día del vendedor

### Vender

*Ventas → Nueva venta.*

1. **Escanea o teclea** el serial o el código interno del aparato. También
   busca por SKU o por nombre.
2. Sale en la lista; pulsa y entra al carrito. Repite con cada aparato.
3. **Teclea el precio pactado** con el cliente. El de al lado, el de
   *referencia*, es el precio de lista y no se toca: la diferencia entre los
   dos se registra sola como descuento (referencia 400, cobras 350 → descuento
   50).
4. Elige el **cliente** (opcional: la venta al público sin datos es lo normal)
   y el **método de pago**.
5. **Cobrar.**

**Cuánto se puede rebajar lo decide el producto.** Cada producto tiene un
descuento máximo en Bs (*Catálogo → Productos*); el punto de venta no deja bajar
de ahí, y avisa en la misma fila con el precio mínimo. Si el producto no tiene
descuento autorizado, se cobra el precio de lista. Tampoco se puede cobrar *por
encima* de la referencia. Dos atajos bajo el precio: *Precio de lista* deshace
la rebaja y *Rebaja máxima* aplica el tope de una vez.

**Métodos de pago.** *Efectivo* no pide nada más. *QR* muestra en pantalla la
imagen del QR de la tienda para que el cliente la escanee, y **exige subir el
respaldo del pago** —una foto o captura del comprobante del banco— antes de
cobrar. *Mixto* es parte en efectivo y parte por QR: los dos campos empiezan
vacíos y **escribes el que sepas** — si el total es 300 y anotas 200 en efectivo,
en el QR aparece 100 (o al revés, si el cliente te dice cuánto va a transferir).
Cualquiera de los dos se puede corregir después: al cambiar uno, el otro se
rehace. La suma tiene que dar exactamente el total.

> Si no hay ningún QR vigente registrado, el punto de venta lo avisa y solo deja
> cobrar en efectivo. Los QR se registran en *Ventas → QR de cobro*, con su
> fecha límite; el día siguiente a esa fecha dejan de ofrecerse solos.

**¿El cliente no está registrado?** Búscalo por su carnet o su nombre y el
sistema mira en dos sitios:

1. **En los clientes.** Si aparece, lo eliges y listo.
2. **En las personas.** Mucha gente ya está registrada aunque nunca haya
   comprado —los trabajadores, por ejemplo—. Si sale ahí, el botón *Usar* le
   crea la ficha de cliente con los datos que ya tiene: no vuelves a teclear
   nada. Si su ficha estaba archivada, se le devuelve la suya con su código y
   su historial.

Solo si no aparece en ninguno de los dos aparece **Registrar nuevo cliente**,
que lo da de alta con lo mínimo (carnet, nombre y un apellido) sin salir de la
venta. Así un cliente que ya existe no acaba con dos fichas y su historial
partido en dos.

**El buscador también muestra lo que no se puede vender**, apagado y con el
motivo: si escaneas la etiqueta de un aparato que ya salió, verás *«Vendido el
… en la venta VTA-…»* en lugar de un «sin resultados» que no explica nada. Si el
código no existe, lo dice con esas palabras. Vender dos veces la misma pieza
sigue siendo imposible, ni siquiera si dos cajas lo intentan a la vez.

En el carrito, cada aparato muestra **su ficha completa** —código, serial,
marca, modelo, SKU, categoría, garantía y ubicación— para que puedas comprobar
que es el que tienes en la mano.

**Quitar un aparato, vaciar el carrito y cobrar piden confirmación.** El repaso
del cobro resume las líneas, el cliente, cómo paga y el total: una venta
registrada solo se deshace anulándola, y la anulación queda en el histórico.

Se cobra en **efectivo, QR o mixto**.

Al cobrar, en un solo movimiento: se emite la venta, los aparatos pasan a
*vendido*, queda el rastro en el kardex y el aviso llega al administrador. O
pasa todo, o no pasa nada — nunca queda media venta.

### El recibo

Al terminar la venta, el mismo recuadro que confirma el cobro trae **Descargar
recibo (PDF)**. Se abre en otra pestaña, así que puedes imprimirlo o mandarlo
por WhatsApp y seguir vendiendo sin cerrar nada.

Sale en formato de ticket (80 mm, el rollo del mostrador) y lleva el código de
la venta, el cliente, cada aparato con su **serial y su garantía**, el desglose
de descuentos y, si el pago fue mixto, cuánto se cobró en efectivo y cuánto por
QR.

¿Se perdió el papel, o el cliente vuelve pidiéndolo para la garantía? El mismo
botón está en *Ventas → Historial*, dentro del detalle de cada venta. Las ventas
anuladas también se pueden reimprimir, pero el recibo lo dice bien grande arriba.

### Anular una venta

Desde el detalle de la venta, con un **motivo**.

Los aparatos vuelven al stock y se pueden revender; la venta **sigue en el
listado**, marcada como anulada, y deja de contar en los reportes. No se borra
nunca: el histórico tiene que seguir cuadrando.

### Registrar un cliente

*Ventas → Clientes → Nuevo.*

Primero **busca a la persona** por carnet, nombre o apellido. Si ya está en el
sistema —por ejemplo porque trabaja aquí— se registra con un clic y sus datos
no se duplican. Si no está, se dan de alta persona y ficha a la vez.

### Los QR de cobro

*Ventas → QR de cobro.*

Cada QR se registra con su **imagen**, el banco, el titular y una **fecha
límite**. Es la fecha la que manda: mientras esté vigente, el punto de venta lo
ofrece; el día después de vencer deja de aparecer, sin que nadie tenga que
acordarse de desactivarlo. El listado avisa de los que caducan dentro de siete
días para renovarlos a tiempo.

Un QR se **archiva**, no se borra: las ventas cobradas con él conservan su
respaldo. El comprobante de cada cobro se consulta desde el detalle de la venta,
en *Ventas → Historial*.

---

## 4. Catálogo

**Categorías** (*Catálogo → Categorías*) — árbol de profundidad libre. Se
reordena y se reorganiza **arrastrando** por el asa de la izquierda: soltar
sobre el centro de otra fila la convierte en subcategoría; soltar arriba o
abajo la deja como hermana. El arrastre se desactiva mientras hay una búsqueda
activa, porque ahí la lista es plana y no hay «antes» ni «después».

**Marcas** (*Catálogo → Marcas*) — nombre y logo.

**Productos** (*Catálogo → Productos*) — el **modelo**, no el aparato: «Smart TV
55" 4K», no el televisor concreto que está en la estantería.

- **SKU**: identificador corto y único. Es la raíz del código interno de cada
  unidad, así que conviene que se entienda (`TVSAM55`).
- **Precio de venta**: el precio de lista. Es el que se propone al vender y el
  que se usa en las compras.
- **Descuento máximo**: lo más que el mostrador puede rebajar de este producto,
  en Bs. Con **0** se cobra siempre el precio de lista. Es el margen de
  negociación que autorizas al vendedor: el punto de venta no deja pasar de ahí.
- **Stock mínimo**: por debajo de él, el producto aparece en las alertas del
  dashboard y de la app.
- **Especificaciones**: una fila por característica (pantalla → 55", panel →
  QLED).

La columna **Disponibles** cuenta solo unidades en stock; vendidas, dañadas o en
garantía no suman.

---

## 5. Comprar mercadería

*Compras → Nueva compra.* Todo en una sola pantalla, con la factura del
proveedor delante.

1. **Proveedor, fecha, número de factura y total pagado.**
2. Por cada renglón de la factura: elige el producto (categoría → marca →
   producto), la **cantidad de unidades** y **lo que pagaste por ese producto
   entero** — el dato que trae la factura, no el precio de una pieza.
3. El semáforo muestra *pagado / asignado / diferencia*. **El botón no se
   habilita hasta que cuadra exactamente.**
4. Registrar.

En ese momento el sistema crea **todas las unidades físicas**: 10 televisores y
10 lavadoras son 20 registros, cada uno con su código interno y su etiqueta
lista para imprimir.

> **Por qué el cuadre tiene que ser exacto.** Si sobra dinero sin asignar, ese
> costo no lo carga ninguna unidad y el inventario acaba valiendo menos de lo
> que costó — con lo que la ganancia sale inflada. El flete y los gastos se
> reparten entre las unidades **al centavo**, en proporción a lo que vale cada
> línea.

### Seriales

El código interno lo pone el sistema; el **serial del fabricante** viene en la
caja. Entra a la compra → **Registrar seriales**: salen todas las unidades
agrupadas por producto, con el código interno a la izquierda y un campo a la
derecha. Se teclean todos y se guardan **de una vez**.

Se pueden dejar en blanco: el aparato se identifica igual por su código interno.

### Etiquetas

- Toda la compra: botón **Imprimir etiquetas** en su detalle.
- Sueltas: desde *Inventario*, por fila o marcando varias.

Tres tamaños (50×25, 70×35 y 100×50 mm) y hasta 5 copias. Las medidas son
milímetros reales: la etiqueta sale del tamaño del adhesivo. Al imprimir
desaparecen los controles y el borde de guía.

> Una compra registrada **no se puede editar**: sus unidades ya están en el
> almacén, o vendidas, con un costo que dejaría de coincidir con lo pagado.

---

## 6. Inventario

**Stock** (*Inventario → Stock*) — vista de catálogo: qué hay disponible por
producto, agrupado por categorías o marcas, con filtros de agotados y de bajo
mínimo. Es la pantalla para contestar «¿tenemos?».

**Unidades** (*Inventario → Unidades*) — aparato por aparato: código interno,
serial, estado, costo, precio y garantía. Al entrar desde un producto se ve
además su ficha completa.

**Estados:** En stock · Reservado · Vendido · Devuelto · Dañado · En garantía ·
Perdido. Se cambian editando la unidad o desde un ajuste en el kardex. Una
unidad nueva entra siempre **en stock**.

**Kardex** (*Inventario → Kardex*) — la historia de cada aparato. Busca (o
escanea) el serial y sale su línea de tiempo completa: cuándo entró, de qué
compra, cada cambio de estado, quién lo hizo y por qué.

Ahí mismo está el **ajuste**, para corregir el estado de una unidad. **Exige un
motivo**: un ajuste sin explicación no sirve de auditoría, que es justo para lo
que existe el kardex.

> El alta manual de una unidad existe para **regularizar** stock que ya estaba
> en la tienda antes del sistema. El camino normal es la compra.

---

## 7. Reportes

*Reportes*, una sola pantalla con atajos de período (hoy / semana / mes / año) o
rango propio:

- Resumen: ventas, aparatos, ingreso, ganancia, ticket promedio y margen.
- Evolución diaria en barras.
- Top de productos, ventas por vendedor y reparto por método de pago.
- Rentabilidad por proveedor: invertido, ingreso, ganancia y % recuperado.
  Es **histórico**, no del período: una compra se recupera a lo largo de meses.
- Valor del inventario que sigue en stock.

**Las ventas anuladas no cuentan** en ningún indicador: devolvieron su
mercadería y su dinero.

La rentabilidad **de una compra concreta** está en el detalle de esa compra, que
es donde tiene contexto.

> La ganancia y los costos solo se ven con el permiso `reportes.ver_costos`. Sin
> él, esas tarjetas se sustituyen por datos de volumen: el precio de compra no
> es información de mostrador.

---

## 8. Personal y accesos

**Personas** — los datos personales de todo el mundo. Es la base: un trabajador
y un cliente pueden ser la misma persona, y su celular se corrige en un solo
sitio.

**Trabajadores** — la ficha laboral: cargo, fecha de ingreso, código.

- **Crear cuenta de acceso** desde el listado. El usuario se arma solo (inicial
  del nombre + apellidos: «Juan Carlos Peña Ríos» → `jpenarios`) y la contraseña
  inicial es el carnet. Si la persona no tiene correo, se genera uno interno que
  solo sirve para entrar.
- **Reiniciar contraseña** para quien ya la tiene: vuelve al carnet.
- **Dar de baja** no borra nada: marca la fecha, bloquea la cuenta y la ficha
  queda consultable. *Reincorporar* conserva el código y la fecha de ingreso
  original. **No puedes darte de baja a ti mismo**: te echaría del sistema en
  ese momento.

**Usuarios y Roles** — cuentas, activación y matriz de permisos por módulo.
El rol `admin` tiene acceso total por diseño; su matriz es informativa. No se
puede quitar el propio rol de admin, ni eliminar al único que queda.

---

## 9. Dashboard y app del teléfono

El **dashboard** muestra los indicadores del día y las últimas ventas. El panel
*Ventas en vivo* se actualiza solo, sin recargar, cuando alguien cobra. Si se
queda quieto, es que el servidor de WebSockets no está corriendo: **no afecta a
las ventas**, que se registran igual.

La **app del teléfono** sirve para dos cosas: **consultar** cómo va la tienda
(dashboard por período, histórico de ventas con búsqueda por serial, catálogo,
personas, compras y avisos) y **vender desde el mostrador**, escaneando la
etiqueta del aparato con la cámara. Anular ventas, recepcionar compras y editar
el catálogo se siguen haciendo en el panel web.

### Instalarla en el teléfono

Se pasa el archivo `app-release.apk` al teléfono (por cable, WhatsApp o
descarga) y se toca para instalarlo. Android pedirá permitir **«instalar
aplicaciones de orígenes desconocidos»** para la app desde la que se abrió: es
normal en una app que no viene de Play Store.

La app ya viene apuntando al servidor de la tienda, así que **funciona con datos
móviles fuera del local**, sin configurar nada al abrirla. Se entra con el mismo
usuario y contraseña del panel web.

> **Para actualizarla, normalmente basta con instalar el APK nuevo encima.** Solo
> si Android se queja de que la aplicación «no se pudo instalar» hay que
> desinstalar antes la anterior; eso pasa cuando el APK se generó en otro
> equipo. Al desinstalar solo se pierde la sesión: los datos están en el
> servidor.

Si al entrar dice que **no se pudo conectar con el servidor**, casi siempre es el
teléfono sin internet o el servidor caído; se comprueba abriendo la dirección del
panel en el navegador del mismo teléfono.

En la pestaña **Catálogo** hay tres solapas:

- **Categorías** — el árbol de la tienda. Tocar una lleva a sus productos,
  incluidos los de sus subcategorías.
- **Marcas** — cada una con sus productos y, sobre todo, **cuántas unidades le
  quedan en stock**. Tocar una filtra el listado por ella.
- **Productos** — búsqueda por nombre, SKU, modelo o **serial**, filtro de
  «solo con stock» y el precio con su rebaja autorizada. Al abrir uno se ven sus
  especificaciones, la garantía y los aparatos concretos que quedan, con su
  código interno y su serial.

En la pestaña **Personas** hay otras tres solapas, y solo aparecen las que tu
cuenta puede ver:

- **Trabajadores** — búsqueda por código, nombre, carnet o cargo, y filtro de
  *En activo · Bajas · Todos*. La ficha muestra su cargo y antigüedad, sus datos,
  su cuenta de acceso (con qué usuario entra y cuándo entró por última vez) y
  cuánto ha vendido.
- **Cargos** — cuánta gente ocupa cada uno, separando los vigentes de las bajas.
  Tocar uno lleva a esos trabajadores.
- **Clientes** — búsqueda por código, nombre o carnet, cuánto ha comprado cada
  uno y sus últimas compras, con enlace al detalle de cada venta.

Y en **Compras**, otras dos:

- **Órdenes** — búsqueda por código, factura o proveedor, y filtro por estado
  (*Borradores · Recepcionadas · Anuladas*). Al abrir una se ve el desglose de
  importes, **cuánto de flete y gastos se reparte entre los aparatos**, y cada
  línea con su costo real, su precio de venta y su margen por pieza. Si ya está
  recepcionada, se pueden desplegar los aparatos que entraron con ella.
- **Proveedores** — a quién llamar, cuánto se le ha comprado y sus últimas
  órdenes. Tocar uno filtra las órdenes por él.

El alta y la edición siguen siendo cosa del panel: en el teléfono se consulta,
no se edita. **Recepcionar una compra tampoco se puede desde la app**: eso se
hace con la mercadería delante, contando cajas y anotando seriales.

### Vender desde el teléfono

El botón **Vender** aparece en cualquier pantalla de la app, abajo a la derecha.

1. **Escanea la etiqueta** del aparato con el botón azul de la cámara. Si el
   código coincide con un serial, entra solo al carrito; si no, sale una lista
   para elegir. También se puede teclear el serial, el código, el SKU o el
   nombre.
2. **Toca el precio** para cambiarlo al que pactaste con el cliente. La
   *referencia* es el precio de lista y es el máximo; por abajo, el límite es el
   descuento autorizado del producto, con atajos para *precio de lista* y
   *rebaja máxima*. La diferencia se registra sola como descuento.
3. **Cobrar** lleva a la pantalla de cobro: cliente, método de pago y notas.
   El cliente se busca por nombre, carnet o código, y funciona igual que en el
   panel, en dos peldaños:
   - Si aparece como **cliente**, tócalo y listo.
   - Si no, salen las **personas ya registradas** que todavía no tienen ficha de
     cliente (por ejemplo, un trabajador). Tocar una **le crea la ficha con sus
     datos**: no hay que volver a teclear carnet ni apellidos. Si su ficha
     estaba archivada, se restaura con su código y su historial de compras.
   - Solo si no aparece en ninguno de los dos se habilita *Registrar nuevo
     cliente*. Es a propósito: dar de alta a alguien que ya existe duplicaría
     su ficha y partiría su historial.
4. Los métodos de pago son **los mismos tres del mostrador**: *Efectivo*, *QR* y
   *Mixto*. En **QR** y **Mixto** se muestra el QR de la tienda para que el
   cliente lo escanee, y hay que **adjuntar la foto del comprobante** antes de
   cobrar. En mixto, al escribir una de las dos cantidades la otra se completa
   con la diferencia.

   > *Tarjeta* y *Transferencia* ya no se ofrecen, ni aquí ni en el panel. Las
   > ventas viejas cobradas así siguen viéndose en el histórico.
5. **Cobrar** registra la venta: los aparatos pasan a *vendido* y salen del
   stock, igual que en el mostrador.

> Si la cámara no está disponible o falta el permiso, la pantalla lo dice y deja
> teclear el serial a mano. Anular una venta sigue haciéndose desde el panel.

#### Si el escáner no mete el aparato al carrito

La primera vez conviene saber que **el escáner lee la etiqueta que imprime el
panel** (Inventario → seleccionar unidades → *Etiquetas*), no el código de barras
que trae la caja del fabricante. Son códigos distintos: el de la caja solo sirve
si además se registró como serial de esa unidad.

Cuando el código leído no entra al carrito, la app **dice por qué** y enseña el
código que leyó:

- **«Vendido»** — ese aparato ya salió. Se indica la fecha y la venta, con un
  botón para abrirla. Si el cliente lo está devolviendo, la devolución se hace
  desde el panel.
- **«Reservado», «Dañado», «En garantía», «Devuelto»** — existe pero no es
  vendible en ese estado, y se explica qué haría falta.
- **«Código no registrado»** — ningún aparato de la tienda tiene ese código.
  Suele ser una de dos cosas: se escaneó el código del fabricante en vez de la
  etiqueta de la tienda, o el aparato no llegó a recepcionarse en su compra.

Ver el código leído en pantalla es la señal de que **la cámara funciona**: si
aparece, el lector no es el problema. Si no lee nada —etiqueta rota, borrosa o
mal impresa—, el botón del **teclado** arriba a la derecha deja escribir el
código a mano y sigue el mismo camino.

### Registrar el serial con la cámara

El serial del fabricante ya no obliga a entrar al panel: se puede registrar desde
el teléfono, con el aparato en la mano.

Entra a **Catálogo → Productos → el producto**. En *Aparatos en stock*, cada
unidad tiene un botón de cámara a la derecha:

1. Tócalo y **apunta al código de barras del fabricante**, el de la caja o el de
   detrás del aparato.
2. La app enseña lo que leyó y en qué unidad lo va a guardar. Revisa y confirma.
3. Queda guardado al instante, sin recargar: se pueden encadenar varios.

Las unidades **sin serial se listan en rojo**, así se ve de un vistazo cuáles
faltan por registrar.

Al confirmar, la app dice **de qué tipo era la etiqueta** («Leído como EAN-13»).
Si ese texto aparece, la cámara hizo su trabajo.

> **Cuidado con los códigos de la caja: un EAN-13 o un UPC no es un serial.**
> Identifican el **modelo**, no el aparato concreto: todos los televisores
> iguales traen el mismo número. Si lo guardas como serial, el siguiente igual
> se rechazará por repetido y ya tendrás uno mal registrado. La app te avisa
> cuando lo leído es de ese tipo. **El serial de verdad suele estar en otra
> etiqueta, junto a «S/N»**, detrás del aparato.

> **Si el serial ya está en otra unidad, la app lo rechaza y dice en cuál.** Casi
> siempre significa que ese aparato ya se registró antes, o que se está
> escaneando el código equivocado (el del modelo en vez del de la pieza).

#### Qué códigos lee la cámara

Lee prácticamente todo lo que llega a la tienda: **Code 128** (las etiquetas que
imprime el panel), **EAN-13, EAN-8, UPC-A y UPC-E** (cajas de fábrica), **Code 39,
Code 93, Codabar, Entrelazado 2 de 5 e ITF-14**, y los bidimensionales **QR, Data
Matrix, PDF417 y Aztec**.

Hay tres simbologías raras que **ningún lector de teléfono reconoce** —Code-11,
MSI y Telepen—, propias de otros rubros y no de electrodomésticos. Y
«Flattermarken», que aparece en los generadores de códigos, **no es un código de
barras**: es la marca de encuadernación del lomo de los libros y no lleva ningún
dato dentro.

Si te topas con una de esas, o con una etiqueta rota, usa el botón del **teclado**
y escribe los dígitos que van impresos bajo las barras.

> Hace falta el permiso de **editar unidades**: sin él, el botón no aparece. El
> registro en lote de una compra entera sigue estando en el panel (§5).

---

## 10. Preguntas frecuentes

**No encuentro un aparato al vender.**
No está *en stock*. Búscalo en el Kardex por su serial: ahí verás en qué estado
está y por qué.

**Me equivoqué en una venta.**
Anúlala con el motivo. Los aparatos vuelven al stock y se pueden volver a
vender.

**Me equivoqué en una compra ya registrada.**
No se puede editar. Si el error es el estado de una unidad, se corrige con un
ajuste en el Kardex; si es de importes, habla con el administrador.

**Vendí un aparato sin serial.**
No pasa nada: su identidad es el código interno de la etiqueta.

**El dashboard en vivo no se mueve.**
Falta el proceso de WebSockets en el servidor. Las ventas se están registrando
igual; recarga la página para verlas.

**No me llegan los avisos al teléfono.**
Falta configurar Firebase. El historial de avisos dentro de la app sigue
funcionando.
