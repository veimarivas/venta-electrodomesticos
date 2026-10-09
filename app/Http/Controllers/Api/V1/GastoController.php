<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\User;
use App\Support\ArqueoDeCaja;
use App\Support\RegistroDeGastos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Gastos de la tienda desde el teléfono. Solo el administrador (`gastos.*`).
 *
 * La regla del cajón (un gasto en efectivo de hoy puede salir del turno
 * abierto) la aplica `RegistroDeGastos`, el mismo que usa el panel.
 */
class GastoController extends Controller
{
    /** Los gastos de un día, con sus totales y lo que hace falta para el formulario. */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['fecha' => ['nullable', 'date']]);
        $fecha = $datos['fecha'] ?? now()->toDateString();

        $gastos = Gasto::query()
            ->with(['beneficiario', 'user'])
            ->delDia($fecha)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $gastos->map(fn (Gasto $g) => $this->gasto($g))->values(),
            'meta' => [
                'fecha' => $fecha,
                'total' => round((float) $gastos->sum('monto'), 2),
                'por_metodo' => collect(Gasto::METODOS)
                    ->map(fn ($e, $clave) => round((float) $gastos->where('metodo_pago', $clave)->sum('monto'), 2)),
                'categorias' => Gasto::CATEGORIAS,
                'metodos' => Gasto::METODOS,
                'personas' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->name])->values(),
                'caja_abierta' => app(ArqueoDeCaja::class)->abierta() !== null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->guardar($request, null);
    }

    /** Edición por POST y no PUT: lleva el comprobante (multipart). */
    public function update(Request $request, Gasto $gasto): JsonResponse
    {
        return $this->guardar($request, $gasto);
    }

    public function destroy(Gasto $gasto): JsonResponse
    {
        app(RegistroDeGastos::class)->archivar($gasto);

        return response()->json(['message' => 'Gasto archivado: ya no cuenta en el resumen.']);
    }

    private function guardar(Request $request, ?Gasto $gasto): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'concepto' => ['required', 'string', 'min:2', 'max:160'],
            'categoria' => ['required', Rule::in(array_keys(Gasto::CATEGORIAS))],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'metodo_pago' => ['required', Rule::in(array_keys(Gasto::METODOS))],
            'beneficiario_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notas' => ['nullable', 'string', 'max:500'],
            'de_caja' => ['nullable', 'boolean'],
            'comprobante' => ['nullable', 'image', 'max:5120'],
        ], [
            'fecha.before_or_equal' => 'Un gasto no puede ser de un día que todavía no llegó.',
        ]);

        try {
            $guardado = app(RegistroDeGastos::class)->guardar(
                [...$datos, 'de_caja' => (bool) ($datos['de_caja'] ?? false)],
                (int) $request->user()->id,
                $gasto,
                $request->file('comprobante'),
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['monto' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'message' => $gasto ? 'Gasto corregido.' : 'Gasto registrado.',
            'data' => $this->gasto($guardado->load(['beneficiario', 'user'])),
        ], $gasto ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function gasto(Gasto $g): array
    {
        return [
            'id' => $g->id,
            'fecha' => $g->fecha?->toDateString(),
            'concepto' => $g->concepto,
            'categoria' => $g->categoria,
            'categoria_texto' => Gasto::CATEGORIAS[$g->categoria] ?? $g->categoria,
            'monto' => (float) $g->monto,
            'metodo_pago' => $g->metodo_pago,
            'metodo_texto' => Gasto::METODOS[$g->metodo_pago] ?? $g->metodo_pago,
            'beneficiario_id' => $g->beneficiario_id,
            'beneficiario' => $g->beneficiario?->name,
            'de_caja' => $g->caja_id !== null,
            'comprobante_url' => $g->comprobante_url,
            'notas' => $g->notas,
            'registrado_por' => $g->user?->name,
        ];
    }
}
