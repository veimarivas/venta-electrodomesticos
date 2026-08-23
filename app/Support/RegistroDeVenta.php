<?php

namespace App\Support;

use App\Events\VentaRegistrada;
use App\Listeners\AvisarVentaRegistrada;
use App\Models\Producto;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Registra una venta: convierte inventario en dinero.
 *
 * Todo ocurre dentro de una transacción — cabecera, líneas, cambio de estado
 * de cada aparato y kardex. O queda la venta completa o no queda nada: una
 * venta a medias dejaría aparatos marcados como vendidos sin comprobante, o
 * un comprobante sin descontar el stock.
 */
class RegistroDeVenta
{
    public function __construct(
        private readonly GeneradorCodigoVenta $generador,
        private readonly Kardex $kardex,
    ) {}

    /**
     * @param  array<int, array{unidad_id: int, precio_unitario: string, descuento?: string}>  $lineas
     * @param  array<string, mixed>  $cabecera  cliente_id, metodo_pago, notas,
     *                                          qr_cobro_id, monto_efectivo,
     *                                          monto_qr, comprobante_qr
     *
     * @throws RuntimeException  Si alguna unidad ya no se puede vender.
     */
    public function registrar(array $lineas, array $cabecera, int $userId): Venta
    {
        if ($lineas === []) {
            throw new RuntimeException('La venta no tiene ningún aparato.');
        }

        try {
            $venta = DB::transaction(function () use ($lineas, $cabecera, $userId): Venta {
                // lockForUpdate bloquea las filas hasta el commit: si dos
                // cajeros escanean el mismo aparato a la vez, el segundo espera
                // y encuentra el estado ya cambiado en vez de venderlo también.
                $unidades = Unidad::whereIn('id', array_column($lineas, 'unidad_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // El tope de rebaja vive en el producto, no en la unidad: se
                // cargan aparte porque lockForUpdate y with() no se llevan.
                $productos = Producto::whereIn('id', $unidades->pluck('producto_id')->unique())
                    ->get()
                    ->keyBy('id');

                $subtotal = 0;
                $descuentoTotal = 0;
                $costoTotal = 0;
                $detalles = [];

                foreach ($lineas as $linea) {
                    $unidad = $unidades->get($linea['unidad_id']);

                    if ($unidad === null) {
                        throw new RuntimeException('Uno de los aparatos ya no existe.');
                    }

                    // Solo se vende lo que está en stock. Un aparato reservado,
                    // dañado o ya vendido no puede salir por caja.
                    if (! $unidad->esVendible()) {
                        throw new RuntimeException(
                            "El aparato {$unidad->codigo_interno} ya no está disponible (".
                            (Unidad::ESTADOS[$unidad->estado] ?? $unidad->estado).').'
                        );
                    }

                    $precio = ProrrateoDeGastos::aCentavos($linea['precio_unitario']);
                    $descuento = ProrrateoDeGastos::aCentavos($linea['descuento'] ?? '0');
                    // El costo se congela aquí: si mañana cambia, la ganancia
                    // histórica no debe moverse.
                    $costo = ProrrateoDeGastos::aCentavos($unidad->costo_unitario);

                    if ($descuento > $precio) {
                        throw new RuntimeException(
                            "El descuento del aparato {$unidad->codigo_interno} supera su precio."
                        );
                    }

                    // Tope de rebaja del producto. Se comprueba también aquí,
                    // no solo en el POS: la autorización de un descuento es
                    // una regla de negocio, y el componente Livewire es solo
                    // una de las puertas de entrada (la API es otra).
                    $topeDescuento = ProrrateoDeGastos::aCentavos(
                        $productos[$unidad->producto_id]->descuento_maximo ?? '0'
                    );

                    if ($descuento > $topeDescuento) {
                        throw new RuntimeException(
                            "El descuento del aparato {$unidad->codigo_interno} supera el máximo autorizado ".
                            'para ese producto ('.ProrrateoDeGastos::aDecimal($topeDescuento).' Bs).'
                        );
                    }

                    $subtotal += $precio;
                    $descuentoTotal += $descuento;
                    $costoTotal += $costo;

                    $detalles[] = [
                        'unidad' => $unidad,
                        'precio' => $precio,
                        'descuento' => $descuento,
                        'costo' => $costo,
                    ];
                }

                $total = $subtotal - $descuentoTotal;

                $pago = $this->repartoDelPago($cabecera, $total);

                $venta = $this->generador->crearCon([
                    ...$pago,
                    'cliente_id' => $cabecera['cliente_id'] ?? null,
                    'user_id' => $userId,
                    'vendida_en' => now(),
                    'subtotal' => ProrrateoDeGastos::aDecimal($subtotal),
                    'descuento' => ProrrateoDeGastos::aDecimal($descuentoTotal),
                    'total' => ProrrateoDeGastos::aDecimal($total),
                    'costo_total' => ProrrateoDeGastos::aDecimal($costoTotal),
                    'ganancia' => ProrrateoDeGastos::aDecimal($total - $costoTotal),
                    'estado' => 'completada',
                    'notas' => $cabecera['notas'] ?? null,
                ]);

                foreach ($detalles as $detalle) {
                    $unidad = $detalle['unidad'];

                    VentaDetalle::create([
                        'venta_id' => $venta->id,
                        'unidad_id' => $unidad->id,
                        // Guardia de la doble venta: se pone al vender y se
                        // suelta al anular, para que el aparato pueda
                        // revenderse si vuelve al stock.
                        'unidad_vendida_id' => $unidad->id,
                        'producto_id' => $unidad->producto_id,
                        'precio_unitario' => ProrrateoDeGastos::aDecimal($detalle['precio']),
                        'costo_unitario' => ProrrateoDeGastos::aDecimal($detalle['costo']),
                        'descuento' => ProrrateoDeGastos::aDecimal($detalle['descuento']),
                        'ganancia' => ProrrateoDeGastos::aDecimal(
                            $detalle['precio'] - $detalle['descuento'] - $detalle['costo']
                        ),
                    ]);

                    $estadoAnterior = $unidad->estado;

                    $unidad->update([
                        'estado' => 'vendido',
                        'vendido_en' => now(),
                        // El precio realmente cobrado, para que la unidad
                        // refleje lo que salió por caja.
                        'precio_venta' => ProrrateoDeGastos::aDecimal($detalle['precio'] - $detalle['descuento']),
                    ]);

                    $this->kardex->cambioDeEstado(
                        $unidad->refresh(),
                        $estadoAnterior,
                        $venta,
                        "Venta {$venta->codigo}"
                    );
                }

                return $venta->fresh();
            });
        } catch (QueryException $e) {
            // El índice único de venta_detalles.unidad_id es la última línea de
            // defensa contra la doble venta. Si salta, el aparato se vendió en
            // otra caja entre la comprobación y el insert.
            if ($this->esUnidadDuplicada($e)) {
                throw new RuntimeException('Uno de los aparatos acaba de venderse en otra caja. Revisa el carrito.');
            }

            throw $e;
        }

        // Fuera de la transacción: el commit ya ocurrió, así que el dashboard
        // nunca puede recibir una venta que acabe revertida. El evento se
        // envía en el acto (ShouldBroadcastNow), sin depender de un worker.
        //
        // Y va envuelto en try/catch porque ShouldBroadcastNow habla con
        // Reverb en esta misma petición: con el servidor de WebSockets caído,
        // la excepción de conexión llegaría al mostrador y le diría al cajero
        // que la venta falló cuando en realidad ya está cobrada y guardada.
        // Que el panel no se entere es un problema menor; que el cajero cobre
        // dos veces, no.
        try {
            VentaRegistrada::dispatch($venta);
        } catch (Throwable $e) {
            Log::warning('La venta se registró pero no pudo anunciarse en vivo.', [
                'venta' => $venta->codigo,
                'error' => $e->getMessage(),
            ]);

            // Laravel emite el broadcast ANTES de correr los oyentes, así que
            // la excepción del WebSocket se llevó por delante el aviso al
            // administrador. Se ejecuta aparte: el push no tiene por qué caerse
            // porque el servidor de WebSockets esté apagado. (La notificación
            // de dentro sigue encolándose, esto solo dispara el oyente.)
            app(AvisarVentaRegistrada::class)->handle(new VentaRegistrada($venta));
        }

        return $venta;
    }

    /**
     * Cómo se reparte el cobro entre caja y banco.
     *
     * El total manda: la suma de lo cobrado en efectivo y por QR tiene que dar
     * exactamente el total de la venta. Un cliente que paga de más recibe
     * cambio (eso es caja, no venta), y uno que paga de menos deja una deuda
     * que este sistema no lleva. Cualquiera de los dos casos sería un arqueo
     * que no cuadra al cierre del día.
     *
     * @param  array<string, mixed>  $cabecera
     * @param  int  $total  En centavos.
     * @return array<string, mixed>
     */
    private function repartoDelPago(array $cabecera, int $total): array
    {
        $metodo = $cabecera['metodo_pago'] ?? 'efectivo';

        if (! array_key_exists($metodo, Venta::METODOS_PAGO)) {
            throw new RuntimeException('El método de pago no es válido.');
        }

        $qrCobroId = $cabecera['qr_cobro_id'] ?? null;
        $comprobante = $cabecera['comprobante_qr'] ?? null;

        // Efectivo puro: no hay banco de por medio.
        if (! in_array($metodo, Venta::METODOS_CON_QR, true)) {
            return [
                'metodo_pago' => $metodo,
                'qr_cobro_id' => null,
                'monto_efectivo' => ProrrateoDeGastos::aDecimal($total),
                'monto_qr' => ProrrateoDeGastos::aDecimal(0),
                'comprobante_qr' => null,
            ];
        }

        $qr = $qrCobroId !== null ? QrCobro::find($qrCobroId) : null;

        if ($qr === null) {
            throw new RuntimeException('Elige el QR con el que cobró el cliente.');
        }

        // Se revalida la vigencia al cobrar, no solo al pintarlo: entre que se
        // abrió el POS y se cobró pudo pasar la medianoche de la fecha límite.
        if (! $qr->esta_vigente) {
            throw new RuntimeException("El QR «{$qr->nombre}» ya no está vigente. Elige otro.");
        }

        if (! is_string($comprobante) || trim($comprobante) === '') {
            throw new RuntimeException('Falta el respaldo del pago por QR.');
        }

        if ($metodo === 'qr') {
            $efectivo = 0;
            $porQr = $total;
        } else {
            $efectivo = ProrrateoDeGastos::aCentavos($cabecera['monto_efectivo'] ?? '0');
            $porQr = ProrrateoDeGastos::aCentavos($cabecera['monto_qr'] ?? '0');

            if ($efectivo < 0 || $porQr < 0) {
                throw new RuntimeException('Los montos del pago mixto no pueden ser negativos.');
            }

            if ($efectivo + $porQr !== $total) {
                throw new RuntimeException(
                    'El efectivo y el QR deben sumar exactamente el total de la venta ('.
                    ProrrateoDeGastos::aDecimal($total).' Bs).'
                );
            }

            if ($porQr === 0) {
                throw new RuntimeException('Un pago mixto necesita una parte cobrada por QR.');
            }
        }

        return [
            'metodo_pago' => $metodo,
            'qr_cobro_id' => $qr->id,
            'monto_efectivo' => ProrrateoDeGastos::aDecimal($efectivo),
            'monto_qr' => ProrrateoDeGastos::aDecimal($porQr),
            'comprobante_qr' => $comprobante,
        ];
    }

    /**
     * Anula una venta y devuelve sus aparatos al stock.
     *
     * Anular NO borra la venta: el histórico y los reportes tienen que seguir
     * cuadrando. Las unidades vuelven a 'en_stock' y cada devolución queda en
     * el kardex.
     */
    public function anular(Venta $venta, string $motivo): int
    {
        if ($venta->esta_anulada) {
            throw new RuntimeException('Esta venta ya estaba anulada.');
        }

        return DB::transaction(function () use ($venta, $motivo): int {
            $devueltas = 0;

            foreach ($venta->detalles()->with('unidad')->get() as $detalle) {
                $unidad = $detalle->unidad;

                if ($unidad === null) {
                    continue;
                }

                $estadoAnterior = $unidad->estado;

                $unidad->update(['estado' => 'devuelto', 'vendido_en' => null]);

                $this->kardex->cambioDeEstado(
                    $unidad->refresh(),
                    $estadoAnterior,
                    $venta,
                    "Anulación de la venta {$venta->codigo}: {$motivo}"
                );

                // Dos movimientos a propósito: primero la devolución (sale de
                // vendido) y luego el retorno al stock. El kardex tiene que
                // contar lo que pasó, no solo dónde acabó.
                $unidad->update(['estado' => 'en_stock']);

                $this->kardex->cambioDeEstado(
                    $unidad->refresh(),
                    'devuelto',
                    $venta,
                    'Vuelve al stock tras la anulación'
                );

                $devueltas++;
            }

            // Se suelta la guardia de la doble venta: las líneas quedan
            // intactas para el histórico, pero los aparatos vuelven a poder
            // venderse ahora que están de nuevo en stock.
            $venta->detalles()->update(['unidad_vendida_id' => null]);

            $venta->update([
                'estado' => 'anulada',
                'anulada_en' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            return $devueltas;
        });
    }

    private function esUnidadDuplicada(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'venta_detalles_unidad_vendida_id_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'unidad_vendida_id'));
    }
}
