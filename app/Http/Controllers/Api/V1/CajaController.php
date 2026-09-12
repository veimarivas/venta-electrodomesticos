<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Support\ArqueoDeCaja;
use App\Support\ProrrateoDeGastos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * El turno de caja desde el teléfono.
 *
 * Abrir, mover efectivo y cerrar el turno ocurren detrás del mostrador, no
 * delante del panel; por eso la API expone las mismas operaciones. El estado
 * que devuelven todas las acciones es el mismo: la app repinta con él.
 */
class CajaController extends Controller
{
    /**
     * Estado del turno: si hay una caja abierta, sus movimientos y lo que
     * debería haber en el cajón (esto último solo con `caja.ver`).
     */
    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $puedeVer = $usuario->can('caja.ver');

        if (! $usuario->can('caja.gestionar') && ! $puedeVer) {
            abort(403);
        }

        return response()->json(['data' => $this->estado($usuario)]);
    }

    public function abrir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'monto_inicial' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            app(ArqueoDeCaja::class)->abrir(
                $request->user()->id,
                $datos['monto_inicial'],
                $datos['notas'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Caja abierta.',
            'data' => $this->estado($request->user()),
        ]);
    }

    public function cerrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'monto_declarado' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $arqueo = app(ArqueoDeCaja::class);
        $caja = $arqueo->abierta();

        if ($caja === null) {
            return response()->json(['message' => 'No hay ninguna caja abierta.'], 422);
        }

        try {
            $cerrada = $arqueo->cerrar(
                $caja,
                $request->user()->id,
                $datos['monto_declarado'],
                $datos['notas'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $diferencia = (float) $cerrada->diferencia;

        return response()->json([
            'message' => match (true) {
                $diferencia === 0.0 => 'Caja cerrada y cuadrada.',
                $diferencia > 0 => 'Caja cerrada. Sobran Bs '.number_format($diferencia, 2, ',', '.').'.',
                default => 'Caja cerrada. Faltan Bs '.number_format(abs($diferencia), 2, ',', '.').'.',
            },
            'data' => $this->estado($request->user()),
        ]);
    }

    /**
     * Anota un ingreso o un retiro de efectivo del turno.
     */
    public function movimiento(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:ingreso,retiro'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'motivo' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        $arqueo = app(ArqueoDeCaja::class);
        $caja = $arqueo->abierta();

        if ($caja === null) {
            return response()->json(['message' => 'No hay ninguna caja abierta.'], 422);
        }

        try {
            $arqueo->registrarMovimiento(
                $caja,
                $request->user()->id,
                $datos['tipo'],
                $datos['monto'],
                $datos['motivo'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $datos['tipo'] === 'retiro'
                ? 'Retiro registrado.'
                : 'Ingreso registrado.',
            'data' => $this->estado($request->user()),
        ]);
    }

    /**
     * La carga que devuelven todas las acciones, para que la app repinte con
     * el mismo contrato.
     *
     * @return array<string, mixed>
     */
    private function estado(\App\Models\User $usuario): array
    {
        $arqueo = app(ArqueoDeCaja::class);
        $puedeVer = $usuario->can('caja.ver');
        $abierta = $arqueo->abierta();

        if ($abierta !== null) {
            $abierta->loadMissing(['abiertaPor', 'movimientos.user']);
        }

        return [
            'puede_gestionar' => $usuario->can('caja.gestionar'),
            'puede_ver' => $puedeVer,
            'abierta' => $abierta === null ? null : [
                'id' => $abierta->id,
                'monto_inicial' => (float) $abierta->monto_inicial,
                'abierta_en' => $abierta->abierta_en?->toIso8601String(),
                'abierta_por' => $abierta->abiertaPor?->name,
                'ventas' => $abierta->ventas()->completadas()->count(),
                'movimientos_neto' => (float) ProrrateoDeGastos::aDecimal(
                    $arqueo->movimientosNetosEnCentavos($abierta)
                ),
                // El cajero no ve lo esperado: se le pide contar, no comparar.
                'esperado' => $puedeVer
                    ? (float) ProrrateoDeGastos::aDecimal($arqueo->esperadoEnCentavos($abierta))
                    : null,
                'sueltas' => $arqueo->ventasSueltas($abierta),
                'movimientos' => $abierta->movimientos->map(fn ($mov) => [
                    'id' => $mov->id,
                    'tipo' => $mov->tipo,
                    'monto' => (float) $mov->monto,
                    'motivo' => $mov->motivo,
                    'registro' => $mov->user?->name,
                    'creado_en' => $mov->created_at?->toIso8601String(),
                ])->values(),
            ],
        ];
    }
}
