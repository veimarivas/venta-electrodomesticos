<?php

namespace App\Support;

use App\Events\SolicitudDeDescuentoCreada;
use App\Events\SolicitudDeDescuentoResuelta;
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

        if (! $unidad->esVendible()) {
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
    public function aprobadaPara(int $unidadId, int $precioEnCentavos): ?SolicitudDescuento
    {
        return SolicitudDescuento::query()
            ->where('unidad_id', $unidadId)
            ->where('estado', 'aprobada')
            ->whereNull('venta_id')
            ->whereNotNull('precio_aprobado')
            ->where('precio_aprobado', '<=', ProrrateoDeGastos::aDecimal($precioEnCentavos))
            ->orderByDesc('precio_aprobado')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Igual que `aprobadaPara`, pero sin bloquear: la usa el POS para saber si
     * una línea ya está cubierta sin abrir una transacción solo para mirar.
     */
    public function aprobadaVigente(int $unidadId, int $precioEnCentavos): ?SolicitudDescuento
    {
        return SolicitudDescuento::query()
            ->where('unidad_id', $unidadId)
            ->where('estado', 'aprobada')
            ->whereNull('venta_id')
            ->whereNotNull('precio_aprobado')
            ->where('precio_aprobado', '<=', ProrrateoDeGastos::aDecimal($precioEnCentavos))
            ->orderByDesc('precio_aprobado')
            ->first();
    }

    /** Marca la autorización como usada por esta venta. */
    public function consumir(SolicitudDescuento $solicitud, Venta $venta, VentaDetalle $detalle): void
    {
        $solicitud->update([
            'estado' => 'consumida',
            'venta_id' => $venta->id,
            'venta_detalle_id' => $detalle->id,
        ]);
    }

    private function anunciarCreacion(SolicitudDescuento $solicitud): void
    {
        try {
            SolicitudDeDescuentoCreada::dispatch($solicitud);
        } catch (Throwable $e) {
            $this->anotarAvisoFallido('La solicitud de descuento se creó pero no pudo anunciarse.', $solicitud, $e);
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
