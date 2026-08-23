<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Http\Resources\MarcaResource;
use App\Http\Resources\ProductoResource;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * Consulta del catálogo desde la app: categorías, marcas y productos.
 *
 * Solo lectura. El alta y la edición se hacen en el panel, donde están el
 * teclado, las imágenes y la vista completa del catálogo; replicar esos
 * formularios en el teléfono duplicaría las validaciones y la superficie que
 * hay que mantener. Aquí lo que se necesita es responder «¿queda alguno?» y
 * «¿a cuánto lo vendemos?» con el cliente delante.
 */
class CatalogoController extends Controller
{
    /**
     * Árbol de categorías aplanado: cada fila lleva su `padre_id` y su `nivel`
     * para que la app lo pinte con una sangría, sin recorrer una estructura
     * anidada para algo que se ve como una lista.
     */
    public function categorias(Request $request): AnonymousResourceCollection
    {
        $categorias = Categoria::query()
            ->withCount(['productos', 'hijos'])
            ->ordenadas()
            ->get();

        $hijosDe = $categorias->groupBy(fn (Categoria $c): int => $c->padre_id ?? 0);

        // El conteo de la rama se calcula una sola vez en memoria: hacerlo con
        // una subconsulta por fila serían tantas consultas como categorías.
        $ordenadas = $this->aplanar($hijosDe, 0, 0);

        return CategoriaResource::collection($ordenadas);
    }

    /**
     * Recorre el árbol en profundidad dejando puestos `nivel` y
     * `productos_rama` (los productos propios más los de toda su descendencia).
     *
     * @param  Collection<int, Collection<int, Categoria>>  $hijosDe
     * @return Collection<int, Categoria>
     */
    private function aplanar(Collection $hijosDe, int $padreId, int $nivel): Collection
    {
        $filas = new Collection;

        foreach ($hijosDe->get($padreId, new Collection) as $categoria) {
            $descendientes = $this->aplanar($hijosDe, $categoria->id, $nivel + 1);

            $categoria->setAttribute('nivel', $nivel);
            $categoria->setAttribute(
                'productos_rama',
                $categoria->productos_count + $descendientes->sum(
                    fn (Categoria $c): int => (int) $c->getAttribute('productos_count')
                )
            );

            // La categoría va delante de su descendencia: así la lista sale ya
            // en el orden en que se dibuja.
            $filas->push($categoria);
            $filas = $filas->concat($descendientes);
        }

        return $filas;
    }

    public function marcas(Request $request): AnonymousResourceCollection
    {
        $marcas = Marca::query()
            ->withCount('productos')
            // Unidades en stock de toda la marca, en una sola consulta.
            ->withCount(['productos as disponibles' => fn ($q) => $q->join(
                'unidades',
                'unidades.producto_id',
                '=',
                'productos.id'
            )->where('unidades.estado', 'en_stock')->whereNull('unidades.deleted_at')])
            ->orderBy('nombre')
            ->get();

        return MarcaResource::collection($marcas);
    }

    /**
     * Listado paginado con búsqueda y filtros.
     */
    public function productos(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'solo_disponibles' => ['nullable', 'boolean'],
            'solo_activos' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $termino = trim($datos['buscar'] ?? '');

        // Entrar en una categoría muestra también lo que cuelga de ella: si no,
        // un padre con el catálogo repartido entre subcategorías se vería vacío.
        $idsRama = [];

        if (isset($datos['categoria_id'])) {
            $categoria = Categoria::find($datos['categoria_id']);
            $idsRama = $categoria === null
                ? []
                : [$categoria->id, ...$categoria->descendientesIds()];
        }

        $productos = $this->consultaBase()
            ->when($idsRama !== [], fn ($q) => $q->whereIn('categoria_id', $idsRama))
            ->when(isset($datos['marca_id']), fn ($q) => $q->where('marca_id', $datos['marca_id']))
            ->when($datos['solo_activos'] ?? false, fn ($q) => $q->activos())
            ->when($termino !== '', fn ($q) => $q->where(function ($sub) use ($termino) {
                $sub->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('sku', 'like', "%{$termino}%")
                    ->orWhere('modelo', 'like', "%{$termino}%")
                    // También por el serial del aparato: en la tienda se
                    // pregunta por la etiqueta que tiene delante.
                    ->orWhereHas('unidades', fn ($u) => $u->where('serial', 'like', "%{$termino}%")
                        ->orWhere('codigo_interno', 'like', "%{$termino}%"));
            }))
            ->when(
                $datos['solo_disponibles'] ?? false,
                fn ($q) => $q->whereHas('unidades', fn ($u) => $u->disponibles())
            )
            ->orderBy('nombre')
            // Desempate estable: sin él dos productos del mismo nombre pueden
            // saltar de página y aparecer duplicados.
            ->orderBy('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return ProductoResource::collection($productos);
    }

    /**
     * Ficha del producto. Las unidades físicas solo viajan si quien pregunta
     * puede verlas: son el inventario serializado, no la ficha comercial.
     */
    public function producto(Request $request, Producto $producto): ProductoResource
    {
        $consulta = $this->consultaBase()->whereKey($producto->id);

        if ($request->user()?->can('unidades.ver')) {
            $consulta->with([
                'unidades' => fn ($q) => $q->disponibles()
                    ->orderBy('codigo_interno')
                    // Tope de seguridad: un producto con cientos de unidades
                    // haría una respuesta enorme para una pantalla que solo
                    // enseña las primeras.
                    ->limit(100),
            ]);
        }

        return (new ProductoResource($consulta->firstOrFail()))->conDetalle();
    }

    /**
     * Base común del listado y la ficha: sin `withCount` de disponibles, el
     * recurso no sabría cuántos quedan.
     */
    private function consultaBase(): \Illuminate\Database\Eloquent\Builder
    {
        return Producto::query()
            ->with(['categoria', 'marca'])
            // Unidades listas para vender: solo las que están `en_stock`. Las
            // reservadas, vendidas o dañadas no cuentan como existencias.
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()]);
    }
}
