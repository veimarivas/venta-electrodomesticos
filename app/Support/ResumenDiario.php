<?php

namespace App\Support;

use App\Models\Gasto;
use App\Models\MovimientoCaja;
use App\Models\PagoCompra;
use App\Models\PagoCredito;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\CarbonInterface;

/**
 * El cierre del día en dinero: qué entró y qué salió, y por dónde.
 *
 * No depende de la caja: con la caja apagada (ver `Ajustes`) es el único
 * control del día, y con ella encendida lo complementa —el arqueo cuenta
 * billetes de un turno; esto cuenta todo el dinero de la fecha, también el del
 * banco—.
 *
 * Ingresos:
 *   · Ventas del día, partidas en efectivo / QR / otros. De una venta a
 *     crédito solo cuenta la cuota inicial: el resto es deuda, no dinero.
 *   · Cuotas cobradas ese día.
 *   · Ingresos sueltos a la caja.
 *
 * Egresos:
 *   · Pagos a proveedores con fecha de ese día (las compras pagadas).
 *   · Gastos (comida, fletes, servicios…), por categoría y por persona.
 *   · Retiros de la caja.
 *   · Devoluciones a clientes hechas ese día.
 *
 * Todo en centavos enteros y se entrega en Bs con dos decimales.
 */
class ResumenDiario
{
    /**
     * @return array<string, mixed>
     */
    public function del(CarbonInterface $dia): array
    {
        $fecha = $dia->toDateString();

        $ventas = $this->ventas($fecha);
        $cobranza = $this->cobranza($fecha);
        $ingresosCaja = $this->movimientos($fecha, 'ingreso');

        $pagos = $this->pagosAProveedores($fecha);
        $gastos = $this->gastos($fecha);
        $retiros = $this->movimientos($fecha, 'retiro');
        $devoluciones = $this->devoluciones($fecha);

        $ingresos = $ventas['cobrado'] + $cobranza['total'] + $ingresosCaja['total'];
        $egresos = $pagos['total'] + $gastos['total'] + $retiros['total'] + $devoluciones['total'];

        // El efectivo del día, para comparar con lo que hay en el cajón. Las
        // devoluciones y los pagos a proveedores no dicen con qué se pagaron,
        // así que no entran en esta cuenta: se enseñan aparte.
        $efectivoEntra = $ventas['efectivo'] + $cobranza['efectivo'] + $ingresosCaja['total'];
        $efectivoSale = $gastos['por_metodo']['efectivo'] + $retiros['total'];

        return [
            'fecha' => $fecha,
            'ingresos' => [
                'total' => $this->bs($ingresos),
                'ventas' => [
                    'cantidad' => $ventas['cantidad'],
                    'cobrado' => $this->bs($ventas['cobrado']),
                    'efectivo' => $this->bs($ventas['efectivo']),
                    'qr' => $this->bs($ventas['qr']),
                    'otros' => $this->bs($ventas['otros']),
                    // Vendido a crédito y todavía no cobrado: no es dinero
                    // del día, se enseña para que no sorprenda la diferencia.
                    'a_credito' => $this->bs($ventas['a_credito']),
                ],
                'cobranza' => [
                    'cantidad' => $cobranza['cantidad'],
                    'total' => $this->bs($cobranza['total']),
                    'efectivo' => $this->bs($cobranza['efectivo']),
                    'otros' => $this->bs($cobranza['total'] - $cobranza['efectivo']),
                ],
                'caja' => [
                    'total' => $this->bs($ingresosCaja['total']),
                    'detalle' => $ingresosCaja['detalle'],
                ],
            ],
            'egresos' => [
                'total' => $this->bs($egresos),
                'proveedores' => [
                    'total' => $this->bs($pagos['total']),
                    'detalle' => $pagos['detalle'],
                ],
                'gastos' => [
                    'total' => $this->bs($gastos['total']),
                    'por_categoria' => $gastos['por_categoria'],
                    'por_metodo' => array_map(fn (int $c) => $this->bs($c), $gastos['por_metodo']),
                    'por_persona' => $gastos['por_persona'],
                    'detalle' => $gastos['detalle'],
                ],
                'caja' => [
                    'total' => $this->bs($retiros['total']),
                    'detalle' => $retiros['detalle'],
                ],
                'devoluciones' => [
                    'cantidad' => $devoluciones['cantidad'],
                    'total' => $this->bs($devoluciones['total']),
                ],
            ],
            'neto' => $this->bs($ingresos - $egresos),
            'efectivo' => [
                'entra' => $this->bs($efectivoEntra),
                'sale' => $this->bs($efectivoSale),
                'neto' => $this->bs($efectivoEntra - $efectivoSale),
            ],
        ];
    }

    /**
     * @return array{cantidad: int, cobrado: int, efectivo: int, qr: int, otros: int, a_credito: int}
     */
    private function ventas(string $fecha): array
    {
        $ventas = Venta::query()
            ->completadas()
            ->whereDate('vendida_en', $fecha)
            ->get(['metodo_pago', 'total', 'total_devuelto', 'monto_efectivo', 'monto_qr']);

        $suma = ['cantidad' => $ventas->count(), 'cobrado' => 0, 'efectivo' => 0, 'qr' => 0, 'otros' => 0, 'a_credito' => 0];

        foreach ($ventas as $venta) {
            // Lo cobrado en su momento: las devoluciones van como egreso del
            // día en que se hacen, no restadas de la venta.
            $original = $this->c($venta->total) + $this->c($venta->total_devuelto);

            [$efectivo, $qr, $otros] = match ($venta->metodo_pago) {
                'efectivo' => [$original, 0, 0],
                'qr' => [0, $original, 0],
                'mixto' => [$this->c($venta->monto_efectivo), $this->c($venta->monto_qr), 0],
                'credito' => [$this->c($venta->monto_efectivo), $this->c($venta->monto_qr), 0],
                default => [0, 0, $original],
            };

            $suma['efectivo'] += $efectivo;
            $suma['qr'] += $qr;
            $suma['otros'] += $otros;
            $suma['cobrado'] += $efectivo + $qr + $otros;

            if ($venta->metodo_pago === 'credito') {
                $suma['a_credito'] += max($original - $efectivo - $qr, 0);
            }
        }

        return $suma;
    }

    /**
     * @return array{cantidad: int, total: int, efectivo: int}
     */
    private function cobranza(string $fecha): array
    {
        $pagos = PagoCredito::query()->whereDate('pagado_en', $fecha)->get(['monto', 'metodo_pago']);

        return [
            'cantidad' => $pagos->count(),
            'total' => (int) $pagos->sum(fn ($p) => $this->c($p->monto)),
            'efectivo' => (int) $pagos->where('metodo_pago', 'efectivo')->sum(fn ($p) => $this->c($p->monto)),
        ];
    }

    /**
     * @return array{total: int, detalle: array<int, array<string, mixed>>}
     */
    private function movimientos(string $fecha, string $tipo): array
    {
        $movimientos = MovimientoCaja::query()
            ->with('user')
            ->where('tipo', $tipo)
            ->whereDate('created_at', $fecha)
            ->orderBy('created_at')
            ->get();

        return [
            'total' => (int) $movimientos->sum(fn ($m) => $this->c($m->monto)),
            'detalle' => $movimientos->map(fn (MovimientoCaja $m): array => [
                'hora' => $m->created_at?->format('H:i'),
                'motivo' => $m->motivo,
                'monto' => $this->bs($this->c($m->monto)),
                'usuario' => $m->user?->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{total: int, detalle: array<int, array<string, mixed>>}
     */
    private function pagosAProveedores(string $fecha): array
    {
        $pagos = PagoCompra::query()
            ->with(['compra.proveedor', 'user'])
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->get();

        return [
            'total' => (int) $pagos->sum(fn ($p) => $this->c($p->monto)),
            'detalle' => $pagos->map(fn (PagoCompra $p): array => [
                'compra' => $p->compra?->codigo,
                'compra_id' => $p->compra_id,
                'proveedor' => $p->compra?->proveedor?->nombre,
                'monto' => $this->bs($this->c($p->monto)),
                'usuario' => $p->user?->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{total: int, por_categoria: array<int, array<string, mixed>>, por_metodo: array<string, int>, por_persona: array<int, array<string, mixed>>, detalle: array<int, array<string, mixed>>}
     */
    private function gastos(string $fecha): array
    {
        $gastos = Gasto::query()
            ->with(['beneficiario', 'user'])
            ->delDia($fecha)
            ->orderBy('id')
            ->get();

        $porMetodo = array_fill_keys(array_keys(Gasto::METODOS), 0);

        foreach ($gastos as $gasto) {
            $porMetodo[$gasto->metodo_pago] = ($porMetodo[$gasto->metodo_pago] ?? 0) + $this->c($gasto->monto);
        }

        return [
            'total' => (int) $gastos->sum(fn ($g) => $this->c($g->monto)),
            'por_categoria' => $gastos->groupBy('categoria')
                ->map(fn ($grupo, $categoria): array => [
                    'categoria' => $categoria,
                    'etiqueta' => Gasto::CATEGORIAS[$categoria] ?? $categoria,
                    'cantidad' => $grupo->count(),
                    'total' => $this->bs((int) $grupo->sum(fn ($g) => $this->c($g->monto))),
                ])
                ->sortByDesc('total')
                ->values()
                ->all(),
            'por_metodo' => $porMetodo,
            // «Para quién» se gastó: un vendedor, un administrador, o la
            // tienda en general.
            'por_persona' => $gastos->groupBy(fn (Gasto $g) => $g->beneficiario_id ?? 0)
                ->map(fn ($grupo): array => [
                    'persona' => $grupo->first()->beneficiario?->name ?? 'La tienda',
                    'cantidad' => $grupo->count(),
                    'total' => $this->bs((int) $grupo->sum(fn ($g) => $this->c($g->monto))),
                ])
                ->sortByDesc('total')
                ->values()
                ->all(),
            'detalle' => $gastos->map(fn (Gasto $g): array => [
                'id' => $g->id,
                'concepto' => $g->concepto,
                'categoria' => Gasto::CATEGORIAS[$g->categoria] ?? $g->categoria,
                'metodo' => Gasto::METODOS[$g->metodo_pago] ?? $g->metodo_pago,
                'persona' => $g->beneficiario?->name,
                'monto' => $this->bs($this->c($g->monto)),
                'de_caja' => $g->caja_id !== null,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{cantidad: int, total: int}
     */
    private function devoluciones(string $fecha): array
    {
        $lineas = VentaDetalle::query()->whereDate('devuelto_en', $fecha)->get(['precio_unitario', 'descuento']);

        return [
            'cantidad' => $lineas->count(),
            'total' => (int) $lineas->sum(fn (VentaDetalle $l) => $l->netoEnCentavos()),
        ];
    }

    private function c(mixed $valor): int
    {
        return ProrrateoDeGastos::aCentavos($valor ?? '0');
    }

    private function bs(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
