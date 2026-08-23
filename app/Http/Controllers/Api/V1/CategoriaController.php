<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición y baja de categorías desde la app.
 *
 * Las reglas son **las mismas del panel** (`App\Livewire\Categorias\Index`). Si
 * aquí fueran más laxas se colarían datos que el otro formulario rechaza, y el
 * catálogo acabaría con dos criterios según por dónde se tocó.
 *
 * La consulta sigue en `CatalogoController`: aquí solo vive lo que escribe.
 */
class CategoriaController extends Controller
{
    use GeneraSlug;

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, null);

        $categoria = Categoria::create($datos);

        return (new CategoriaResource($this->ficha($categoria->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Categoria $categoria): CategoriaResource
    {
        $datos = $this->validar($request, $categoria);

        $categoria->update($datos);

        return new CategoriaResource($this->ficha($categoria->id));
    }

    /**
     * Recarga la categoría con todo lo que `CategoriaResource` da por hecho.
     *
     * Además de los conteos, el recurso lee `nivel` y `productos_rama`, que en
     * el listado los deja puestos el controlador al aplanar el árbol. Aquí se
     * devuelve una sola fila, así que hay que calcularlos a mano.
     *
     * Laravel no exige atributos en un modelo recién creado, así que sin esto
     * el alta parecería funcionar y la edición devolvería un 500.
     */
    private function ficha(int $id): Categoria
    {
        $categoria = Categoria::query()
            ->withCount(['productos', 'hijos'])
            ->findOrFail($id);

        // Profundidad: se sube por los padres hasta la raíz. El tope evita que
        // un ciclo en los datos —que la validación impide crear, pero que pudo
        // entrar por otra vía— deje esto girando para siempre.
        $nivel = 0;
        $padreId = $categoria->padre_id;

        for ($salto = 0; $padreId !== null && $salto < 20; $salto++) {
            $nivel++;
            $padreId = Categoria::whereKey($padreId)->value('padre_id');
        }

        $categoria->nivel = $nivel;

        // Los productos de toda la rama, no solo los directos: un padre con el
        // catálogo repartido entre sus hijas parecería vacío.
        $categoria->productos_rama = Producto::query()
            ->whereIn('categoria_id', [$categoria->id, ...$categoria->descendientesIds()])
            ->count();

        return $categoria;
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        // Misma guarda que el panel: una categoría con ramas colgando dejaría
        // sus subcategorías huérfanas y desaparecidas del árbol.
        if ($categoria->hijos()->exists()) {
            throw ValidationException::withMessages([
                'categoria' => 'Esta categoría tiene subcategorías. Muévelas o elimínalas primero.',
            ]);
        }

        $categoria->delete();

        return response()->json(['mensaje' => 'Categoría eliminada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Categoria $categoria): array
    {
        // Una categoría no puede colgar de sí misma ni de una de sus propias
        // ramas: el árbol quedaría en un ciclo y el listado se colgaría al
        // recorrerlo.
        $padresProhibidos = [];

        if ($categoria !== null) {
            $padresProhibidos = [...$categoria->descendientesIds(), $categoria->id];
        }

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            // Opcional a propósito: en el teléfono nadie quiere teclear un
            // slug. Si no viene, se deriva del nombre.
            'slug' => [
                'nullable', 'string', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('categorias', 'slug')->ignore($categoria?->id)->whereNull('deleted_at'),
            ],
            'padre_id' => [
                'nullable', 'integer',
                Rule::exists('categorias', 'id')->whereNull('deleted_at'),
                Rule::notIn($padresProhibidos),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'posicion' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una categoría con este slug.',
            'padre_id.not_in' => 'Una categoría no puede colgar de sí misma ni de una de sus subcategorías.',
        ]);

        $datos['slug'] = $this->slugUnico(
            $datos['slug'] ?? null,
            $datos['nombre'],
            Categoria::query(),
            $categoria?->id,
        );

        $datos['posicion'] ??= 0;
        $datos['activo'] ??= true;

        return $datos;
    }
}
