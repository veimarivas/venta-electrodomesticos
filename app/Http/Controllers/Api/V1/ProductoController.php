<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Alta, edición y baja de productos desde la app.
 *
 * Mismas reglas que el panel (`App\Livewire\Productos\Index`).
 *
 * Borrar un producto es **borrado lógico**: sus unidades físicas y las ventas
 * que las incluyen siguen apuntando aquí, y el histórico tiene que poder
 * mostrarlas. Por eso no hay guarda contra unidades existentes — no se pierde
 * nada, el producto solo deja de ofrecerse.
 */
class ProductoController extends Controller
{
    use GeneraSlug;

    /**
     * Especificaciones validadas de la última petición, en el formato que las
     * manda la app (lista de pares). `validar()` las extrae del payload y
     * `store`/`update` las guardan en la tabla.
     *
     * @var array<int, array{clave?: string|null, valor?: string|null}>
     */
    private array $especificacionesNuevas = [];

    public function store(Request $request): JsonResponse
    {
        $producto = Producto::create($this->validar($request, null));

        $this->sincronizarEspecificaciones($producto, $this->especificacionesNuevas);

        return (new ProductoResource($this->ficha($producto->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Producto $producto): ProductoResource
    {
        $imagenAnterior = $producto->imagen;
        $datos = $this->validar($request, $producto);

        $producto->update($datos);

        // El archivo anterior se borra DESPUÉS de guardar: al revés, si la
        // escritura falla, el producto se queda sin foto y sin archivo.
        if (array_key_exists('imagen', $datos) && $imagenAnterior && $datos['imagen'] !== $imagenAnterior) {
            Storage::disk('public')->delete($imagenAnterior);
        }

        $this->sincronizarEspecificaciones($producto, $this->especificacionesNuevas);

        return new ProductoResource($this->ficha($producto->id));
    }

    /**
     * Recarga el producto con lo que `ProductoResource` da por hecho.
     *
     * El recurso lee `disponibles`, un `withCount` con alias. Laravel no exige
     * atributos en un modelo recién creado, así que el alta parecía funcionar;
     * pero al editar se devuelve un modelo *leído* de la base y ahí falta el
     * conteo, `MissingAttributeException` y respuesta 500.
     */
    private function ficha(int $id): Producto
    {
        return Producto::query()
            ->with(['categoria', 'marca', 'especificaciones'])
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->withCount(['unidades as vendidas' => fn ($q) => $q->where('estado', 'vendido')])
            ->findOrFail($id);
    }

    public function destroy(Producto $producto): JsonResponse
    {
        // La imagen NO se borra: el borrado es lógico y restaurar el producto
        // desde el panel debe devolverlo completo, no sin foto.
        $producto->delete();

        return response()->json(['mensaje' => 'Producto eliminado.']);
    }

    /**
     * Devuelve al catálogo un producto archivado. El `{producto}` se resuelve
     * a mano porque el model binding normal no encuentra borrados suaves.
     */
    public function restaurar(Request $request, int $producto): JsonResponse
    {
        $producto = Producto::withTrashed()->findOrFail($producto);

        $producto->restore();

        return response()->json(['mensaje' => 'Producto restaurado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Producto $producto): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'slug' => [
                'nullable', 'string', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('productos', 'slug')->ignore($producto?->id)->whereNull('deleted_at'),
            ],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->whereNull('deleted_at')],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')],
            'modelo' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'especificaciones' => ['nullable', 'array', 'max:40'],
            'especificaciones.*.clave' => ['nullable', 'string', 'max:60'],
            'especificaciones.*.valor' => ['nullable', 'string', 'max:200'],
            'precio_venta' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // Nunca por encima del precio: un descuento mayor dejaría vender el
            // aparato gratis o con importe negativo.
            'descuento_maximo' => ['required', 'numeric', 'min:0', 'lte:precio_venta'],
            'stock_minimo' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'meses_garantia' => ['nullable', 'integer', 'min:0', 'max:240'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'quitar_imagen' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
            'tiene_serial' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe un producto con este slug.',
            'descuento_maximo.lte' => 'La rebaja máxima no puede superar al precio.',
        ]);

        $this->especificacionesNuevas = $datos['especificaciones'] ?? [];

        $guardar = [
            'nombre' => $datos['nombre'],
            'slug' => $this->slugUnico(
                $datos['slug'] ?? null,
                $datos['nombre'],
                Producto::query(),
                $producto?->id,
            ),
            'categoria_id' => $datos['categoria_id'],
            'marca_id' => $datos['marca_id'] ?? null,
            'modelo' => $datos['modelo'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'precio_venta' => $datos['precio_venta'],
            'descuento_maximo' => $datos['descuento_maximo'],
            'stock_minimo' => $datos['stock_minimo'] ?? $producto?->stock_minimo ?? 0,
            'meses_garantia' => $datos['meses_garantia'] ?? $producto?->meses_garantia ?? 0,
            'activo' => $datos['activo'] ?? $producto?->activo ?? true,
            'tiene_serial' => $datos['tiene_serial'] ?? $producto?->tiene_serial ?? true,
        ];

        if ($request->hasFile('imagen')) {
            $guardar['imagen'] = $request->file('imagen')->store('productos', 'public');
        } elseif ($datos['quitar_imagen'] ?? false) {
            $guardar['imagen'] = null;
        }

        return $guardar;
    }

    /**
     * Guarda las características del producto en la tabla
     * `producto_especificaciones`. Se reemplazan enteras: la edición es libre y
     * el orden lo fija la `posicion`.
     *
     * @param  array<int, array{clave?: string|null, valor?: string|null}>  $filas
     */
    private function sincronizarEspecificaciones(Producto $producto, array $filas): void
    {
        $producto->especificaciones()->delete();

        $posicion = 0;

        foreach ($filas as $fila) {
            $clave = trim((string) ($fila['clave'] ?? ''));

            if ($clave === '') {
                continue;
            }

            $valor = trim((string) ($fila['valor'] ?? ''));

            $producto->especificaciones()->create([
                'clave' => $clave,
                'valor' => $valor === '' ? null : $valor,
                'posicion' => $posicion++,
            ]);
        }
    }
}
