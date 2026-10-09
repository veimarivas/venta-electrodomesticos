<?php

namespace App\Support;

use App\Models\VentaDetalle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Qué vendió cada vendedor y a qué precio, para el seguimiento.
 *
 * Cada aparato vendido se compara contra **su precio de lista del momento**
 * (`venta_detalles.precio_lista`): si se cobró menos es descuento, si se cobró
 * más es sobreprecio. Así se ve quién rebaja de más y quién vende por encima,
 * que es lo que la tienda quiere vigilar.
 *
 * Solo ventas completadas y aparatos que siguen vendidos: un aparato devuelto
 * ya no es una venta de nadie.
 */
class SeguimientoDeVendedores
{
    /**
     * @return array<int, array<string, mixed>> Un elemento por vendedor, del que más vendió al que menos.
     */
    public function entre(CarbonInterface $desde, CarbonInterface $hasta, ?int $vendedorId = null): array
    {
        $lineas = VentaDetalle::query()
            ->with(['venta.user', 'producto'])
            ->whereNull('devuelto_en')
            ->whereHas('venta', fn ($q) => $q->completadas()
                ->whereBetween('vendida_en', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
                ->when($vendedorId !== null, fn ($v) => $v->where('user_id', $vendedorId)))
            ->get();

        return $lineas
            ->groupBy(fn (VentaDetalle $l) => $l->venta->user_id)
            ->map(fn (Collection $delVendedor) => $this->vendedor($delVendedor))
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, VentaDetalle>  $lineas
     * @return array<string, mixed>
     */
    private function vendedor(Collection $lineas): array
    {
        $usuario = $lineas->first()->venta->user;
        $cifras = $this->cifras($lineas);

        return [
            'vendedor_id' => $usuario?->id,
            'vendedor' => $usuario?->name ?? 'Sin vendedor',
            'ventas' => $lineas->pluck('venta_id')->unique()->count(),
            ...$cifras,
            'productos' => $lineas
                ->groupBy('producto_id')
                ->map(fn (Collection $delProducto): array => [
                    'producto_id' => $delProducto->first()->producto_id,
                    'producto' => $delProducto->first()->producto?->nombre ?? 'Producto',
                    ...$this->cifras($delProducto),
                ])
                ->sortByDesc('unidades')
                ->values()
                ->all(),
            // Las ventas que se apartaron de la lista, de la más reciente a la
            // más antigua: lo que hay que mirar una por una.
            'desvios' => $lineas
                ->filter(fn (VentaDetalle $l) => $this->diferencia($l) !== 0)
                ->sortByDesc(fn (VentaDetalle $l) => $l->venta->vendida_en)
                ->take(50)
                ->map(fn (VentaDetalle $l): array => [
                    'venta_id' => $l->venta_id,
                    'codigo' => $l->venta->codigo,
                    'fecha' => $l->venta->vendida_en?->format('Y-m-d H:i'),
                    'producto' => $l->producto?->nombre ?? 'Producto',
                    'lista' => $this->bs($this->lista($l)),
                    'cobrado' => $this->bs($this->cobrado($l)),
                    'diferencia' => $this->bs($this->diferencia($l)),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, VentaDetalle>  $lineas
     * @return array<string, int|float>
     */
    private function cifras(Collection $lineas): array
    {
        $lista = 0;
        $cobrado = 0;
        $descuento = 0;
        $sobreprecio = 0;
        $conDescuento = 0;
        $conSobreprecio = 0;

        foreach ($lineas as $linea) {
            $diferencia = $this->diferencia($linea);

            $lista += $this->lista($linea);
            $cobrado += $this->cobrado($linea);

            if ($diferencia < 0) {
                $descuento += -$diferencia;
                $conDescuento++;
            } elseif ($diferencia > 0) {
                $sobreprecio += $diferencia;
                $conSobreprecio++;
            }
        }

        return [
            'unidades' => $lineas->count(),
            'total' => $this->bs($cobrado),
            'total_lista' => $this->bs($lista),
            'descuento' => $this->bs($descuento),
            'con_descuento' => $conDescuento,
            'sobreprecio' => $this->bs($sobreprecio),
            'con_sobreprecio' => $conSobreprecio,
            // Positivo: vendió por encima de la lista en conjunto.
            'balance' => $this->bs($sobreprecio - $descuento),
        ];
    }

    private function lista(VentaDetalle $linea): int
    {
        return ProrrateoDeGastos::aCentavos($linea->precio_lista ?? $linea->precio_unitario);
    }

    private function cobrado(VentaDetalle $linea): int
    {
        return $linea->netoEnCentavos();
    }

    /** Cobrado menos lista: negativo es rebaja, positivo es sobreprecio. */
    private function diferencia(VentaDetalle $linea): int
    {
        return $this->cobrado($linea) - $this->lista($linea);
    }

    private function bs(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
