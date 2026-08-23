<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarcaResource;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición y baja de marcas desde la app.
 *
 * Mismas reglas que el panel (`App\Livewire\Marcas\Index`).
 *
 * **Marca no tiene borrado lógico**: eliminarla la borra de verdad. Por eso la
 * guarda contra productos asociados no es cosmética — la clave foránea es
 * `restrictOnDelete` y sin avisar antes el fallo llegaría como un error de base
 * de datos.
 */
class MarcaController extends Controller
{
    use GeneraSlug;

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, null);

        $marca = Marca::create($datos);

        return (new MarcaResource($this->ficha($marca->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Marca $marca): MarcaResource
    {
        $logoAnterior = $marca->logo_ruta;
        $datos = $this->validar($request, $marca);

        $marca->update($datos);

        // Se borra el anterior DESPUÉS de guardar el nuevo: al revés, si la
        // escritura falla, la marca se queda sin logo y sin archivo.
        if (array_key_exists('logo_ruta', $datos) && $logoAnterior && $datos['logo_ruta'] !== $logoAnterior) {
            Storage::disk('public')->delete($logoAnterior);
        }

        return new MarcaResource($this->ficha($marca->id));
    }

    /**
     * Recarga la marca con los conteos que `MarcaResource` da por hecho:
     * `productos_count` y `disponibles` (unidades en stock de toda la marca).
     *
     * Laravel no exige atributos en un modelo recién creado, así que sin esto
     * el alta parecería funcionar y la edición devolvería un 500.
     */
    private function ficha(int $id): Marca
    {
        return Marca::query()
            ->withCount('productos')
            ->withCount(['productos as disponibles' => fn ($q) => $q->join(
                'unidades',
                'unidades.producto_id',
                '=',
                'productos.id'
            )->where('unidades.estado', 'en_stock')->whereNull('unidades.deleted_at')])
            ->findOrFail($id);
    }

    public function destroy(Marca $marca): JsonResponse
    {
        $marca->loadCount('productos');

        if ($marca->productos_count > 0) {
            throw ValidationException::withMessages([
                'marca' => "No se puede eliminar: {$marca->productos_count} producto(s) usan esta marca.",
            ]);
        }

        if ($marca->logo_ruta) {
            Storage::disk('public')->delete($marca->logo_ruta);
        }

        $marca->delete();

        return response()->json(['mensaje' => 'Marca eliminada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Marca $marca): array
    {
        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('marcas', 'nombre')->ignore($marca?->id),
            ],
            'slug' => [
                'nullable', 'string', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('marcas', 'slug')->ignore($marca?->id),
            ],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Para dejar la marca sin logo sin tener que subir otro.
            'quitar_logo' => ['nullable', 'boolean'],
            'activa' => ['nullable', 'boolean'],
        ], [
            'nombre.unique' => 'Ya existe una marca con este nombre.',
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una marca con este slug.',
        ]);

        $slug = $this->slugUnico(
            $datos['slug'] ?? null,
            $datos['nombre'],
            Marca::query(),
            $marca?->id,
        );

        $guardar = [
            'nombre' => $datos['nombre'],
            'slug' => $slug,
            'activa' => $datos['activa'] ?? $marca?->activa ?? true,
        ];

        if ($request->hasFile('logo')) {
            $guardar['logo_ruta'] = $request->file('logo')->store('marcas', 'public');
        } elseif ($datos['quitar_logo'] ?? false) {
            $guardar['logo_ruta'] = null;
        }

        return $guardar;
    }
}
