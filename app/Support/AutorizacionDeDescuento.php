<?php

namespace App\Support;

use App\Events\SolicitudDeDescuentoCreada;
use App\Events\SolicitudDeDescuentoResuelta;
use App\Listeners\AvisarSolicitudDeDescuento;
use App\Models\Producto;
use App\Models\SolicitudDescuento;
use App\Models\Unidad;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Autorizaciones para vender por debajo del mínimo del producto.
 *
 * El vendedor rebaja hasta el tope del producto por su cuenta. Bajar más —sin
 * llegar por debajo del costo— exige permiso del administrador. Este servicio es
 * la única puerta: crea la solicitud con la foto del momento, la resuelve y,
 * sobre todo, decide si una venta puede apoyarse en una autorización.
 *
 * La llamada a Reverb va envuelta en try/catch, igual que en `RegistroDeVenta`:
 * con el WebSocket caído el POS tiene que poder seguir pidiendo autorización,
 * aunque el aviso en vivo no llegue.
 */
class AutorizacionDeDescuento
{
    /**
     * Pide autorización para vender una unidad por debajo de su mínimo.
     *
     * @throws RuntimeException  Si el precio no necesita autorización, si queda
     *                           por debajo del costo, o si el aparato ya no se
     *                           puede vender.
     */
    public function solicitar(Unidad $unidad, string $precioSolicitado, int $userId): SolicitudDescuento
    {
        $unidad->loadMissing('producto');

        // Agregar al carrito deja el aparato en `reservado`: para su propio
        // cajero sigue siendo vendible. Para los demás, no.
        $esMiReserva = $unidad->estado === 'reservado'
            && (int) $unidad->reservado_por === $userId;

        if (! $unidad->esVendible() && ! $esMiReserva) {
            throw new RuntimeException('Ese aparato ya no está disponible para vender.');
        }

        $lista = ProrrateoDeGastos::aCentavos($unidad->precio_venta);
        $tope = ProrrateoDeGastos::aCentavos($unidad->producto?->descuento_maximo ?? '0');
        $costo = ProrrateoDeGastos::aCentavos($unidad->costo_unitario);
        $pedido = ProrrateoDeGastos::aCentavos($precioSolicitado);

        if ($pedido >= $lista - $tope) {
            throw new RuntimeException('Ese precio entra en el descuento permitido: no necesita autorización.');
        }

        if ($pedido < $costo) {
            throw new RuntimeException('No se puede pedir autorización para vender por debajo del costo.');
        }

        // Pedir dos veces lo mismo llenaría la bandeja del administrador de
        // avisos idénticos: se reutiliza la solicitud pendiente.
        $existente = SolicitudDescuento::query()
            ->where('unidad_id', $unidad->id)
            ->where('user_id', $userId)
            ->where('estado', 'pendiente')
            ->where('precio_solicitado', ProrrateoDeGastos::aDecimal($pedido))
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $solicitud = SolicitudDescuento::create([
            'unidad_id' => $unidad->id,
            'producto_id' => $unidad->producto_id,
            'user_id' => $userId,
            'precio_lista' => ProrrateoDeGastos::aDecimal($lista),
            'descuento_maximo' => ProrrateoDeGastos::aDecimal($tope),
            'costo_unitario' => ProrrateoDeGastos::aDecimal($costo),
            'precio_solicitado' => ProrrateoDeGastos::aDecimal($pedido),
            'estado' => 'pendiente',
        ]);

        $this->anunciarCreacion($solicitud);

        return $solicitud;
    }

    /**
     * Pide autorización para vender N unidades de un producto a un precio.
     *
     * Es la que usa la venta por cantidad: el vendedor pacta un precio por la
     * línea entera y, en los productos con serial, todavía no sabe qué aparatos
     * exactos se lleva el cliente (se escanean al confirmar). Una sola solicitud
     * cubre las N; al cobrar, cada unidad del producto gasta una.
     *
     * El costo de la foto es el **mayor** de las unidades en juego: así el
     * precio aprobado no deja ninguna por debajo de lo que costó.
     *
     * @param  array<int, int>  $unidadIds  Las que el vendedor tiene apartadas
     *                                      en su carrito (para tomar su costo).
     *
     * @throws RuntimeException  Si el precio no necesita autorización o queda
     *                           por debajo del costo.
     */
    public function solicitarPorProducto(
        Producto $producto,
        string $precioSolicitado,
        int $cantidad,
        int $userId,
        array $unidadIds = [],
    ): SolicitudDescuento {
        $cantidad = max(1, $cantidad);

        $lista = ProrrateoDeGastos::aCentavos((string) $this->listaDe($producto));
        $tope = ProrrateoDeGastos::aCentavos($producto->descuento_maximo ?? '0');
        $costo = $this->costoPisoDe($producto, $unidadIds);
        $pedido = ProrrateoDeGastos::aCentavos($precioSolicitado);

        if ($pedido >= $lista - $tope) {
            throw new RuntimeException('Ese precio entra en el descuento permitido: no necesita autorización.');
        }

        if ($pedido < $costo) {
            throw new RuntimeException('No se puede pedir autorización para vender por debajo del costo.');
        }

        $existente = SolicitudDescuento::query()
            ->whereNull('unidad_id')
            ->where('producto_id', $producto->id)
            ->where('user_id', $userId)
            ->where('estado', 'pendiente')
            ->where('precio_solicitado', ProrrateoDeGastos::aDecimal($pedido))
            ->where('cantidad', $cantidad)
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $solicitud = SolicitudDescuento::create([
            'unidad_id' => null,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'usadas' => 0,
            'user_id' => $userId,
            'precio_lista' => ProrrateoDeGastos::aDecimal($lista),
            'descuento_maximo' => ProrrateoDeGastos::aDecimal($tope),
            'costo_unitario' => ProrrateoDeGastos::aDecimal($costo),
            'precio_solicitado' => ProrrateoDeGastos::aDecimal($pedido),
            'estado' => 'pendiente',
        ]);

        $this->anunciarCreacion($solicitud);

        return $solicitud;
    }

    /**
     * Precios que el vendedor puede proponer cuando quiere bajar del mínimo.
     *
     * Tres escalones por debajo del mínimo, en pasos redondos (de 5, 10 o 50
     * según el precio), sin llegar nunca al costo: el vendedor elige uno con un
     * toque en vez de inventar una cifra delante del cliente. El costo no viaja:
     * solo los precios que ya lo respetan.
     *
     * @param  array<int, int>  $unidadIds
     * @return array{precio_lista: float, precio_minimo: float, sugerencias: array<int, float>}
     */
    public function sugerencias(Producto $producto, array $unidadIds = []): array
    {
        $lista = ProrrateoDeGastos::aCentavos((string) $this->listaDe($producto));
        $tope = ProrrateoDeGastos::aCentavos($producto->descuento_maximo ?? '0');
        $minimo = max(0, $lista - $tope);
        $costo = $this->costoPisoDe($producto, $unidadIds);

        // El paso: un 3 % de la lista, redondeado a una cifra cómoda.
        $redondeo = match (true) {
            $lista >= 500000 => 5000,   // desde Bs 5.000, de 50 en 50
            $lista >= 50000 => 1000,    // desde Bs 500, de 10 en 10
            default => 500,             // de 5 en 5
        };
        $paso = max($redondeo, (int) (round($lista * 0.03 / $redondeo) * $redondeo));

        // El primero cae en una cifra redonda por debajo del mínimo.
        $primero = (int) (floor(($minimo - 1) / $redondeo) * $redondeo);

        $sugerencias = [];

        for ($i = 0, $precio = $primero; $i < 3; $i++, $precio -= $paso) {
            if ($precio < max($costo, 1)) {
                break;
            }

            $sugerencias[] = (float) ProrrateoDeGastos::aDecimal($precio);
        }

        return [
            'precio_lista' => (float) ProrrateoDeGastos::aDecimal($lista),
            'precio_minimo' => (float) ProrrateoDeGastos::aDecimal($minimo),
            'sugerencias' => $sugerencias,
        ];
    }

    /** Precio de lista del producto: el del día o, si no hay, el del catálogo. */
    private function listaDe(Producto $producto): float
    {
        $precio = app(PreciosDelDia::class)->precioVigente($producto->id);

        return $precio > 0 ? $precio : (float) ($producto->precio_venta ?? 0);
    }

    /**
     * El costo más alto de las unidades en juego: las del carrito si se
     * conocen, o todas las que se pueden vender del producto.
     *
     * @param  array<int, int>  $unidadIds
     */
    private function costoPisoDe(Producto $producto, array $unidadIds): int
    {
        $consulta = Unidad::query()->where('producto_id', $producto->id);

        $consulta = $unidadIds !== []
            ? $consulta->whereIn('id', $unidadIds)
            : $consulta->whereIn('estado', ['en_stock', 'reservado']);

        return ProrrateoDeGastos::aCentavos((string) ($consulta->max('costo_unitario') ?? '0'));
    }

    /**
     * Aprueba (con el precio pedido o con uno sugerido) o rechaza la solicitud.
     *
     * @param  bool  $aprobar  true = aprobar; false = rechazar.
     * @param  string|null  $precio  Precio sugerido; null o vacío usa el pedido.
     *
     * @throws RuntimeException  Si ya estaba resuelta o el precio aprobado no
     *                           tiene sentido (por debajo del costo o por
     *                           encima de la lista).
     */
    public function resolver(
        SolicitudDescuento $solicitud,
        bool $aprobar,
        ?string $precio,
        ?string $motivo,
        int $userId,
    ): SolicitudDescuento {
        if (! $solicitud->estaPendiente()) {
            throw new RuntimeException('Esa solicitud ya fue resuelta.');
        }

        return DB::transaction(function () use ($solicitud, $aprobar, $precio, $motivo, $userId): SolicitudDescuento {
            $nota = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;

            if (! $aprobar) {
                $solicitud->update([
                    'estado' => 'rechazada',
                    'resuelto_por' => $userId,
                    'resuelto_en' => now(),
                    'motivo' => $nota ?? 'Sin autorización.',
                ]);
            } else {
                $aprobado = ProrrateoDeGastos::aCentavos(
                    $precio !== null && trim($precio) !== '' ? $precio : $solicitud->precio_solicitado
                );

                $costo = ProrrateoDeGastos::aCentavos($solicitud->costo_unitario);
                $lista = ProrrateoDeGastos::aCentavos($solicitud->precio_lista);

                if ($aprobado < $costo) {
                    throw new RuntimeException('El precio autorizado no puede quedar por debajo del costo.');
                }

                if ($aprobado > $lista) {
                    throw new RuntimeException('El precio autorizado no puede superar el precio de lista.');
                }

                $solicitud->update([
                    'estado' => 'aprobada',
                    'precio_aprobado' => ProrrateoDeGastos::aDecimal($aprobado),
                    'resuelto_por' => $userId,
                    'resuelto_en' => now(),
                    'motivo' => $nota,
                ]);
            }

            $solicitud->refresh();

            $this->anunciarResolucion($solicitud);

            return $solicitud;
        });
    }

    /** El solicitante retira su propia solicitud antes de que la resuelvan. */
    public function cancelar(SolicitudDescuento $solicitud, int $userId): void
    {
        if (! $solicitud->estaPendiente()) {
            return;
        }

        if ((int) $solicitud->user_id !== $userId) {
            throw new RuntimeException('Esa solicitud no es tuya.');
        }

        $solicitud->update(['estado' => 'cancelada']);
    }

    /**
     * Autorización aprobada y sin usar que respalde este precio.
     *
     * Es la comprobación que hace `RegistroDeVenta` al cobrar: no se fía de lo
     * que traiga el POS, busca en la base. Se bloquea la fila hasta el commit
     * para que dos cajas no gasten la misma autorización a la vez.
     */
    public function aprobadaPara(
        int $unidadId,
        int $precioEnCentavos,
        ?int $userId = null,
        array $tomadas = [],
    ): ?SolicitudDescuento {
        return $this->buscarAprobada($unidadId, $precioEnCentavos, $userId, $tomadas, bloquear: true);
    }

    /**
     * Igual que `aprobadaPara`, pero sin bloquear: la usa el POS para saber si
     * una línea ya está cubierta sin abrir una transacción solo para mirar.
     *
     * @param  array<int, int>  $tomadas  Unidades ya asignadas por solicitud
     *                                    en este mismo carrito (id => cuántas).
     */
    public function aprobadaVigente(
        int $unidadId,
        int $precioEnCentavos,
        ?int $userId = null,
        array $tomadas = [],
    ): ?SolicitudDescuento {
        return $this->buscarAprobada($unidadId, $precioEnCentavos, $userId, $tomadas, bloquear: false);
    }

    /**
     * Primero la autorización de ese aparato; si no hay, una por producto del
     * mismo vendedor a la que le queden unidades.
     *
     * `$tomadas` cuenta lo que ya se asignó en la venta en curso: una solicitud
     * de 2 unidades no puede cubrir 3 líneas del mismo carrito aunque en la
     * base todavía figuren 0 usadas (se gastan al guardar la venta).
     *
     * @param  array<int, int>  $tomadas
     */
    private function buscarAprobada(
        int $unidadId,
        int $precioEnCentavos,
        ?int $userId,
        array $tomadas,
        bool $bloquear,
    ): ?SolicitudDescuento {
        $techo = ProrrateoDeGastos::aDecimal($precioEnCentavos);

        $porAparato = SolicitudDescuento::query()
            ->where('unidad_id', $unidadId)
            ->where('estado', 'aprobada')
            ->whereNull('venta_id')
            ->whereNotNull('precio_aprobado')
            ->where('precio_aprobado', '<=', $techo)
            ->orderByDesc('precio_aprobado')
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->first();

        if ($porAparato !== null && ! isset($tomadas[$porAparato->id])) {
            return $porAparato;
        }

        $productoId = Unidad::query()->whereKey($unidadId)->value('producto_id');

        if ($productoId === null || $userId === null) {
            return null;
        }

        return SolicitudDescuento::query()
            ->whereNull('unidad_id')
            ->where('producto_id', $productoId)
            ->where('user_id', $userId)
            ->where('estado', 'aprobada')
            ->whereColumn('usadas', '<', 'cantidad')
            ->whereNotNull('precio_aprobado')
            ->where('precio_aprobado', '<=', $techo)
            ->orderByDesc('precio_aprobado')
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->get()
            ->first(fn (SolicitudDescuento $s): bool => $s->restantes() - ($tomadas[$s->id] ?? 0) > 0);
    }

    /**
     * Marca la autorización como usada por esta venta.
     *
     * La de un aparato se gasta entera. La de un producto suma una unidad
     * usada y solo pasa a `consumida` cuando se gastan todas: si el cliente se
     * llevó dos de tres, la tercera sigue valiendo para ese vendedor.
     */
    public function consumir(SolicitudDescuento $solicitud, Venta $venta, VentaDetalle $detalle): void
    {
        if (! $solicitud->esPorProducto()) {
            $solicitud->update([
                'estado' => 'consumida',
                'venta_id' => $venta->id,
                'venta_detalle_id' => $detalle->id,
            ]);

            return;
        }

        // Cada línea de la venta trae su propia copia de la fila: sin releerla,
        // dos líneas guardarían «1 usada» en vez de 2.
        $solicitud->refresh();

        $usadas = (int) $solicitud->usadas + 1;

        $solicitud->update([
            'usadas' => $usadas,
            'estado' => $usadas >= (int) $solicitud->cantidad ? 'consumida' : 'aprobada',
            'venta_id' => $solicitud->venta_id ?? $venta->id,
            'venta_detalle_id' => $detalle->id,
        ]);
    }

    private function anunciarCreacion(SolicitudDescuento $solicitud): void
    {
        try {
            SolicitudDeDescuentoCreada::dispatch($solicitud);
        } catch (Throwable $e) {
            $this->anotarAvisoFallido('La solicitud de descuento se creó pero no pudo anunciarse.', $solicitud, $e);

            // Laravel emite el broadcast ANTES de correr los oyentes, así que la
            // excepción de Reverb se llevaba por delante el aviso al
            // administrador: la solicitud llegaba a la bandeja, pero la campana
            // del panel y los avisos de la app quedaban vacíos. Se ejecuta
            // aparte: el aviso no tiene por qué caerse porque el servidor de
            // WebSockets esté apagado. Es el mismo apaño que usa RegistroDeVenta.
            try {
                app(AvisarSolicitudDeDescuento::class)->handle(new SolicitudDeDescuentoCreada($solicitud));
            } catch (Throwable) {
                // El aviso es secundario; la solicitud ya está creada y en la bandeja.
            }
        }
    }

    private function anunciarResolucion(SolicitudDescuento $solicitud): void
    {
        try {
            SolicitudDeDescuentoResuelta::dispatch($solicitud);
        } catch (Throwable $e) {
            $this->anotarAvisoFallido('La solicitud de descuento se resolvió pero no pudo anunciarse.', $solicitud, $e);
        }
    }

    /**
     * Deja constancia de un aviso que no salió, sin dejar que el propio log
     * tumbe la operación.
     *
     * Con Reverb caído el dispatch lanza; si encima el archivo de log no se
     * puede escribir —permisos de `storage`—, un `Log::warning` sin proteger
     * volvería a lanzar y el administrador vería un error al aprobar algo que
     * en realidad ya se aprobó. El aviso es secundario: la solicitud y su
     * resolución se guardan igual.
     */
    private function anotarAvisoFallido(string $mensaje, SolicitudDescuento $solicitud, Throwable $e): void
    {
        try {
            Log::warning($mensaje, [
                'solicitud' => $solicitud->id,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
            // Si ni el log se puede escribir, no hay nada más que hacer aquí.
        }
    }
}
