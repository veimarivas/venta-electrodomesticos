<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrabajadorResource;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Support\GeneradorCodigoTrabajador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición, baja y reincorporación de trabajadores desde la app.
 *
 * Mismas reglas que el panel (`App\Livewire\Trabajadores\Index`).
 *
 * **Un trabajador no se borra: se da de baja.** La ficha guarda la fecha y el
 * motivo, y las ventas y compras que registró siguen apuntando a ella. Por eso
 * la baja va en su propia ruta y no en un `DELETE`: no es lo mismo.
 */
class TrabajadorController extends Controller
{
    /**
     * Da de alta un trabajador, por uno de dos caminos.
     *
     * Con `persona_id` se le abre la ficha laboral a alguien que ya está en el
     * sistema; sin él, se registran persona y ficha de una sola vez. Repetir
     * los datos de alguien que ya existe chocaría contra el índice único del
     * carnet, así que el primer camino no es un atajo: es el correcto cuando la
     * persona ya está.
     */
    public function store(Request $request): JsonResponse
    {
        $laborales = $request->validate($this->reglasLaborales());

        $trabajador = $request->filled('persona_id')
            ? $this->desdePersonaExistente($request, $laborales)
            : $this->conPersonaNueva($request, $laborales);

        return (new TrabajadorResource($trabajador->load('persona', 'cargo')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Solo los datos laborales: cargo y fecha de ingreso.
     *
     * El nombre, el carnet y el celular viven en `personas` y se editan por
     * `PersonaController`, que es de donde los leen también su ficha de cliente
     * y su cuenta de acceso.
     */
    public function update(Request $request, Trabajador $trabajador): TrabajadorResource
    {
        $datos = $request->validate($this->reglasLaborales());

        $trabajador->update([
            'cargo_id' => (int) $datos['cargo_id'],
            'fecha_ingreso' => $datos['fecha_ingreso'],
        ]);

        return new TrabajadorResource($trabajador->fresh()->load('persona', 'cargo'));
    }

    /**
     * Cierra la ficha y, con ella, la cuenta de acceso.
     *
     * Van juntas a propósito: un trabajador dado de baja que sigue pudiendo
     * entrar al sistema es exactamente lo que la baja pretende evitar. La
     * cuenta **no se borra**, solo se desactiva, porque las ventas y compras
     * que registró siguen apuntando a ella.
     */
    public function baja(Request $request, Trabajador $trabajador): TrabajadorResource
    {
        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $trabajador->load('persona.user');

        if (! $trabajador->esta_activo) {
            throw ValidationException::withMessages([
                'trabajador' => 'Este trabajador ya estaba dado de baja.',
            ]);
        }

        // Darse de baja a uno mismo cerraría la sesión en el acto —el
        // middleware `active` expulsa a las cuentas desactivadas— y dejaría al
        // administrador fuera del sistema a mitad de la operación.
        if ($trabajador->persona->user?->is($request->user())) {
            throw ValidationException::withMessages([
                'trabajador' => 'No puedes darte de baja a ti mismo. Pídeselo a otro administrador.',
            ]);
        }

        DB::transaction(function () use ($trabajador, $datos): void {
            $motivo = trim((string) ($datos['motivo'] ?? ''));

            $trabajador->update([
                'fecha_baja' => now()->toDateString(),
                'motivo_baja' => $motivo !== '' ? $motivo : null,
            ]);

            $cuenta = $trabajador->persona->user;

            if ($cuenta !== null && $cuenta->is_active) {
                $cuenta->update(['is_active' => false]);
            }
        });

        return new TrabajadorResource($trabajador->fresh()->load('persona', 'cargo'));
    }

    /**
     * Reincorpora, y con ello reactiva su cuenta.
     *
     * Simétrico a la baja: sin esto, volver a contratar a alguien lo dejaría
     * sin poder entrar y habría que acordarse de reactivarlo a mano desde el
     * módulo de usuarios.
     */
    public function reactivar(Trabajador $trabajador): TrabajadorResource
    {
        $trabajador->load('persona.user');

        if ($trabajador->esta_activo) {
            throw ValidationException::withMessages([
                'trabajador' => 'Este trabajador ya está en activo.',
            ]);
        }

        DB::transaction(function () use ($trabajador): void {
            $trabajador->update(['fecha_baja' => null, 'motivo_baja' => null]);

            $cuenta = $trabajador->persona->user;

            if ($cuenta !== null && ! $cuenta->is_active) {
                $cuenta->update(['is_active' => true]);
            }
        });

        return new TrabajadorResource($trabajador->fresh()->load('persona', 'cargo'));
    }

    /**
     * @param  array<string, mixed>  $laborales
     */
    private function desdePersonaExistente(Request $request, array $laborales): Trabajador
    {
        $datos = $request->validate([
            'persona_id' => ['required', 'integer', Rule::exists('personas', 'id')->whereNull('deleted_at')],
        ]);

        $persona = Persona::with('trabajador')->findOrFail($datos['persona_id']);

        if ($persona->trabajador !== null) {
            throw ValidationException::withMessages([
                'persona_id' => 'Esta persona ya está registrada como trabajador.',
            ]);
        }

        return app(GeneradorCodigoTrabajador::class)->crearCon([
            'persona_id' => $persona->id,
            'cargo_id' => (int) $laborales['cargo_id'],
            'fecha_ingreso' => $laborales['fecha_ingreso'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $laborales
     */
    private function conPersonaNueva(Request $request, array $laborales): Trabajador
    {
        $datos = $request->validate(
            PersonaController::reglas(null),
            PersonaController::mensajes(),
        );

        // Persona y ficha laboral se crean juntas: si falla la segunda, no debe
        // quedar una persona suelta que el usuario cree que no registró.
        return DB::transaction(function () use ($datos, $laborales): Trabajador {
            $persona = Persona::create(PersonaController::aColumnas($datos));

            return app(GeneradorCodigoTrabajador::class)->crearCon([
                'persona_id' => $persona->id,
                'cargo_id' => (int) $laborales['cargo_id'],
                'fecha_ingreso' => $laborales['fecha_ingreso'],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasLaborales(): array
    {
        return [
            'cargo_id' => ['required', 'integer', Rule::exists('cargos', 'id')],
            // No se admite una fecha futura ni una anterior a 1950: casi
            // siempre son un dedazo al teclear el año.
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today', 'after:1950-01-01'],
        ];
    }
}
