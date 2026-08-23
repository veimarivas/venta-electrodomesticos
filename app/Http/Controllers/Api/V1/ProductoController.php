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

    public function store(Request $request): JsonResponse
    {
        $producto = Producto::create($this->validar($request, null));

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
            ->with(['categoria', 'marca'])
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
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
            'sku' => [
                'required', 'string', 'regex:/^[A-Za-z0-9\-]{3,40}$/',
                Rule::unique('productos', 'sku')->ignore($producto?->id)->whereNull('deleted_at'),
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
        ], [
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe un producto con este slug.',
            'sku.regex' => 'El SKU solo puede contener letras, números y guiones (3 a 40 caracteres).',
            'sku.unique' => 'Ya existe un producto con este SKU.',
            'descuento_maximo.lte' => 'La rebaja máxima no puede superar al precio.',
        ]);

        $guardar = [
            'nombre' => $datos['nombre'],
            'slug' => $this->slugUnico(
                $datos['slug'] ?? null,
                $datos['nombre'],
                Producto::query(),
                $producto?->id,
            ),
            // En mayúsculas, como en el panel: el SKU se compara a ojo contra
            // la etiqueta y «tv-55» y «TV-55» tienen que ser el mismo.
            'sku' => strtoupper($datos['sku']),
            'categoria_id' => $datos['categoria_id'],
            'marca_id' => $datos['marca_id'] ?? null,
            'modelo' => $datos['modelo'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'precio_venta' => $datos['precio_venta'],
            'descuento_maximo' => $datos['descuento_maximo'],
            'stock_minimo' => $datos['stock_minimo'] ?? $producto?->stock_minimo ?? 0,
            'meses_garantia' => $datos['meses_garantia'] ?? $producto?->meses_garantia ?? 0,
            'activo' => $datos['activo'] ?? $producto?->activo ?? true,
        ];

        if (array_key_exists('especificaciones', $datos)) {
            $guardar['especificaciones'] = $this->limpiarEspecificaciones($datos['especificaciones'] ?? []);
        }

        if ($request->hasFile('imagen')) {
            $guardar['imagen'] = $request->file('imagen')->store('productos', 'public');
        } elseif ($datos['quitar_imagen'] ?? false) {
            $guardar['imagen'] = null;
        }

        return $guardar;
    }

    /**
     * Convierte las filas del formulario al **mapa** que guarda la columna.
     *
     * La app las manda como lista de pares porque así se pintan en orden, pero
     * en la base viven como objeto JSON (`{"Pantalla": "55 pulgadas"}`), que es
     * el formato que escribe el panel y que `ProductoResource` sabe leer.
     * Guardar aquí la lista de pares dejaría dos formatos en la misma columna
     * según por dónde se hubiera creado el producto.
     *
     * Una característica **sin valor** se guarda como `true`, no se descarta:
     * es la bandera del panel para cosas que se tienen o no se tienen
     * («Bluetooth»). Sin clave, en cambio, no hay nada que decir.
     *
     * @param  array<int, array{clave?: string|null, valor?: string|null}>  $filas
     * @return array<string, string|true>|null
     */
    private function limpiarEspecificaciones(array $filas): ?array
    {
        $especificaciones = [];

        foreach ($filas as $fila) {
            $clave = trim((string) ($fila['clave'] ?? ''));
            $valor = trim((string) ($fila['valor'] ?? ''));

            if ($clave === '') {
                continue;
            }

            $especificaciones[$clave] = $valor === '' ? true : $valor;
        }

        // NULL y no `[]`: un array vacío se guarda como `{}` y la ficha
        // enseñaría una sección de especificaciones sin nada dentro.
        return $especificaciones === [] ? null : $especificaciones;
    }
}
