<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CargoResource;
use App\Http\Resources\TrabajadorResource;
use App\Models\Cargo;
use App\Models\Trabajador;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta del personal desde la app: trabajadores y cargos.
 *
 * Solo lectura, como el resto de la API. Dar de alta a alguien exige su carnet,
 * su cargo y crearle la cuenta de acceso: es trabajo de oficina, no de teléfono.
 */
class PersonalController extends Controller
{
    public function cargos(Request $request): AnonymousResourceCollection
    {
        $cargos = Cargo::query()
            ->withCount('trabajadores')
            // Los vigentes aparte: un cargo con diez fichas de las que ocho son
            // antiguos trabajadores no está ocupado por diez personas.
            ->withCount(['trabajadores as activos' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get();

        return CargoResource::collection($cargos);
    }

    public function trabajadores(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'estado' => ['nullable', 'in:activos,bajas,todos'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $estado = $datos['estado'] ?? 'activos';

        $trabajadores = Trabajador::query()
            ->with(['persona', 'cargo'])
            ->buscar($datos['buscar'] ?? null)
            ->when(isset($datos['cargo_id']), fn ($q) => $q->where('cargo_id', $datos['cargo_id']))
            ->when($estado === 'activos', fn ($q) => $q->activos())
            ->when($estado === 'bajas', fn ($q) => $q->dadosDeBaja())
            ->orderBy('codigo')
            // Desempate estable: sin él dos fichas del mismo código pueden
            // saltar de página y aparecer duplicadas.
            ->orderBy('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return TrabajadorResource::collection($trabajadores);
    }

    public function trabajador(Request $request, Trabajador $trabajador): TrabajadorResource
    {
        $trabajador->load(['cargo', 'persona.user.roles']);

        // Las ventas que registró se calculan aquí y no en el recurso: son dos
        // agregados sobre `ventas`, y el recurso no debe lanzar consultas.
        $vendido = Venta::query()
            ->completadas()
            ->when(
                $trabajador->persona?->user !== null,
                fn (Builder $q) => $q->where('user_id', $trabajador->persona->user->id),
                // Sin cuenta de acceso no pudo registrar ninguna venta.
                fn (Builder $q) => $q->whereRaw('1 = 0')
            )
            // El alias no puede llamarse `total`: la tabla ya tiene una columna
            // con ese nombre y `sum(total)` quedaría ambiguo.
            ->selectRaw('count(*) as cuantas, coalesce(sum(total), 0) as importe')
            ->first();

        $trabajador->setAttribute('ventas_registradas', (int) $vendido->cuantas);
        $trabajador->setAttribute('importe_vendido', (float) $vendido->importe);

        return (new TrabajadorResource($trabajador))->conDetalle();
    }
}
