<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CargoResource;
use App\Models\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición y baja de cargos desde la app.
 *
 * Mismas reglas que el panel (`App\Livewire\Cargos\Index`).
 *
 * **Cargo no tiene borrado lógico**: eliminarlo lo borra de verdad, y la clave
 * foránea de `trabajadores` es `restrictOnDelete`.
 */
class CargoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $cargo = Cargo::create($this->validar($request, null));

        return (new CargoResource($this->ficha($cargo->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Cargo $cargo): CargoResource
    {
        $cargo->update($this->validar($request, $cargo));

        return new CargoResource($this->ficha($cargo->id));
    }

    public function destroy(Cargo $cargo): JsonResponse
    {
        $cargo->loadCount([
            'trabajadores as trabajadores_total' => fn ($q) => $q->withTrashed(),
        ]);

        // Se cuentan TODAS las fichas, también las dadas de baja: aunque el
        // cargo ya no tenga personal vigente, los registros históricos siguen
        // apuntando a él y el borrado reventaría igual contra la clave foránea.
        if ($cargo->trabajadores_total > 0) {
            throw ValidationException::withMessages([
                'cargo' => "No se puede eliminar: {$cargo->trabajadores_total} trabajador(es) tienen o tuvieron este cargo.",
            ]);
        }

        $cargo->delete();

        return response()->json(['mensaje' => 'Cargo eliminado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Cargo $cargo): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'min:3', 'max:80',
                // Letras, espacios y los signos habituales de un puesto
                // («Técnico de instalación», «Chofer / repartidor»).
                'regex:/^[\p{L}0-9\s\'\-\/\.]+$/u',
                Rule::unique('cargos', 'nombre')->ignore($cargo?->id),
            ],
        ], [
            'nombre.regex' => 'El nombre del cargo solo puede contener letras, números y los signos - / .',
            'nombre.unique' => 'Ya existe un cargo con este nombre.',
        ]);
    }

    /**
     * Recarga el cargo con los conteos que `CargoResource` da por hecho.
     *
     * Laravel no exige atributos en un modelo recién creado, así que sin esto
     * el alta parecería funcionar y la edición devolvería un 500.
     */
    private function ficha(int $id): Cargo
    {
        // Mismos alias que el listado (`PersonalController::cargos`): el
        // recurso lee `trabajadores_count` y `activos`.
        return Cargo::query()
            ->withCount('trabajadores')
            ->withCount(['trabajadores as activos' => fn ($q) => $q->activos()])
            ->findOrFail($id);
    }
}
