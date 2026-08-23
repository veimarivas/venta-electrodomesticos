<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProveedorResource;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Proveedores desde la app: consulta y mantenimiento.
 *
 * Las reglas de escritura son **las mismas del panel**
 * (`App\Livewire\Proveedores\Index`). Van en este mismo controlador y no en uno
 * aparte para poder reutilizar [consultaBase]: el recurso lee cuatro agregados
 * —lo invertido, la última compra, las unidades— que solo existen si la
 * consulta los añadió, y tenerlos en dos sitios sería tenerlos desincronizados.
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
    public function store(Request $request): JsonResponse
    {
        $proveedor = Proveedor::create($this->validar($request, null));

        return (new ProveedorResource($this->ficha($request, $proveedor->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Proveedor $proveedor): ProveedorResource
    {
        $proveedor->update($this->validar($request, $proveedor));

        return new ProveedorResource($this->ficha($request, $proveedor->id));
    }

    public function destroy(Proveedor $proveedor): JsonResponse
    {
        $proveedor->loadCount('compras');

        // Un proveedor con compras registradas dejaría el histórico de costos
        // sin origen: de dónde salió la mercadería es parte del kardex.
        // Desactivarlo es lo que se busca casi siempre.
        if ($proveedor->compras_count > 0) {
            throw ValidationException::withMessages([
                'proveedor' => "No se puede eliminar: tiene {$proveedor->compras_count} compra(s) registrada(s). Desactívalo en su lugar.",
            ]);
        }

        $proveedor->delete();

        return response()->json(['mensaje' => 'Proveedor eliminado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Proveedor $proveedor): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'nit' => [
                'nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/',
                Rule::unique('proveedores', 'nit')->ignore($proveedor?->id)->whereNull('deleted_at'),
            ],
            'contacto' => ['nullable', 'string', 'min:3', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s\-]{7,30}$/'],
            'correo' => ['nullable', 'email:rfc', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nit.regex' => 'El NIT solo puede contener números, letras y guiones.',
            'nit.unique' => 'Ya existe un proveedor con este NIT.',
            'telefono.regex' => 'El teléfono solo puede contener números, espacios, guiones y el signo +.',
        ]);

        $guardar = ['nombre' => trim($datos['nombre'])];

        // Los opcionales vacíos van como NULL, nunca como cadena vacía: dos
        // proveedores sin NIT chocarían contra el índice único si se guardara
        // una cadena vacía.
        foreach (['nit', 'contacto', 'telefono', 'correo', 'direccion', 'notas'] as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));

            $guardar[$campo] = $valor === '' ? null : $valor;
        }

        $guardar['activo'] = $datos['activo'] ?? $proveedor?->activo ?? true;

        return $guardar;
    }

    /**
     * Recarga el proveedor con los agregados que `ProveedorResource` da por
     * hecho cuando el usuario puede ver compras.
     *
     * Laravel no exige atributos en un modelo recién creado, así que sin esto
     * el alta parecería funcionar y la edición devolvería un 500.
     */
    private function ficha(Request $request, int $id): Proveedor
    {
        return $this->consultaBase($request)->findOrFail($id);
    }

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
