<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProveedorResource;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta de proveedores desde la app.
 *
 * Solo lectura, como el resto de la API. Lo que se necesita en el almacén es a
 * quién llamar y qué se le ha comprado, no dar de alta un proveedor nuevo.
 */
class ProveedorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:activos,inactivos,todos'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $estado = $datos['estado'] ?? 'activos';

        $proveedores = $this->consultaBase($request)
            ->when($estado === 'activos', fn ($q) => $q->where('activo', true))
            ->when($estado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->buscar($datos['buscar'] ?? null)
            ->orderBy('nombre')
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return ProveedorResource::collection($proveedores);
    }

    public function show(Request $request, string $proveedor): ProveedorResource
    {
        $ficha = $this->consultaBase($request)
            // withTrashed a mano: un proveedor archivado sigue teniendo
            // historial de compras y su ficha debe poder abrirse.
            ->withTrashed()
            ->when(
                $request->user()?->can('compras.ver') ?? false,
                fn ($q) => $q->with([
                    'compras' => fn ($c) => $c->with(['proveedor', 'user'])
                        ->withCount(['detalles', 'unidades'])
                        ->orderByDesc('fecha_compra')
                        ->orderByDesc('id')
                        ->limit(10),
                ])
            )
            ->findOrFail((int) $proveedor);

        return (new ProveedorResource($ficha))->conDetalle();
    }

    /**
     * Base común. Los agregados de compras se calculan con subconsultas: traer
     * las compras de cada proveedor para sumarlas sería traer el histórico
     * entero por página.
     */
    private function consultaBase(Request $request): Builder
    {
        $consulta = Proveedor::query();

        if (! ($request->user()?->can('compras.ver') ?? false)) {
            return $consulta;
        }

        // Solo las recepcionadas: un borrador todavía no es dinero puesto ni
        // mercadería que haya entrado al almacén.
        //
        // La columna va cualificada porque el conteo de unidades hace un join
        // con `unidades`, que también tiene un `estado`: sin el prefijo, MySQL
        // rechaza la consulta por ambigua.
        $recepcionadas = fn ($q) => $q->where('compras.estado', 'recepcionada');

        return $consulta
            ->withCount(['compras as compras_count' => $recepcionadas])
            ->withSum(['compras as invertido' => $recepcionadas], 'total')
            ->withMax(['compras as ultima_compra' => $recepcionadas], 'fecha_compra')
            ->withCount(['compras as unidades_compradas' => fn ($q) => $recepcionadas($q)->join(
                'unidades',
                'unidades.compra_id',
                '=',
                'compras.id'
            )->whereNull('unidades.deleted_at')]);
    }
}
