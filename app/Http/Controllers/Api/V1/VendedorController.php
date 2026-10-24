<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trabajador;
use App\Models\User;
use App\Support\CuentaDeTrabajador;
use App\Support\DiasDeTrabajo;
use App\Support\EquipoDeVentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * El personal desde el teléfono del administrador: crear la cuenta de quien
 * entra, reiniciar el acceso de quien lo olvidó y armar los días de trabajo
 * del mes. Las mismas reglas que el panel (`CuentaDeTrabajador`,
 * `DiasDeTrabajo`).
 */
class VendedorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $mes = $this->mes($datos['mes'] ?? null);

        return response()->json([
            'data' => app(EquipoDeVentas::class)->listado($mes->year, $mes->month, $datos['buscar'] ?? null),
            'meta' => [
                'mes' => $mes->format('Y-m'),
                'rol_por_defecto' => CuentaDeTrabajador::ROL_POR_DEFECTO,
            ],
        ]);
    }

    /** Crea la cuenta: usuario = inicial y apellidos, contraseña = carnet. */
    public function crearCuenta(Request $request, Trabajador $trabajador): JsonResponse
    {
        $datos = $request->validate([
            'rol' => ['nullable', 'string', Rule::exists('roles', 'name')],
        ]);

        $trabajador->loadMissing('persona.user');

        if (! $trabajador->esta_activo) {
            throw ValidationException::withMessages(['trabajador' => 'No se crean cuentas para trabajadores dados de baja.']);
        }

        if ($trabajador->persona->user !== null) {
            throw ValidationException::withMessages(['trabajador' => 'Este trabajador ya tiene cuenta de usuario.']);
        }

        $servicio = app(CuentaDeTrabajador::class);
        $usuario = $servicio->crear($trabajador->persona, $datos['rol'] ?? CuentaDeTrabajador::ROL_POR_DEFECTO);

        return response()->json([
            'acceso' => [
                'usuario' => $usuario->name,
                'contrasena_temporal' => $servicio->passwordDe($trabajador->persona),
            ],
            'mensaje' => "Cuenta «{$usuario->name}» creada. Al entrar se le pedirá una contraseña nueva.",
        ], 201);
    }

    /** Reinicia el acceso de quien lo olvidó (vuelve al carnet). */
    public function reiniciar(Request $request, Trabajador $trabajador): JsonResponse
    {
        $trabajador->loadMissing('persona.user');
        $usuario = $trabajador->persona?->user;

        if ($usuario === null) {
            throw ValidationException::withMessages(['trabajador' => 'Este trabajador todavía no tiene cuenta de usuario.']);
        }

        if ($usuario->is($request->user())) {
            throw ValidationException::withMessages(['trabajador' => 'Tu propia contraseña se cambia desde tu perfil.']);
        }

        $servicio = app(CuentaDeTrabajador::class);
        $servicio->reiniciar($usuario, $trabajador->persona);

        return response()->json([
            'acceso' => [
                'usuario' => $usuario->fresh()->name,
                'contrasena_temporal' => $servicio->passwordDe($trabajador->persona),
            ],
            'mensaje' => 'Acceso reiniciado. Se cerraron sus sesiones y al entrar se le pedirá una contraseña nueva.',
        ]);
    }

    /**
     * Días de trabajo de un mes. Quien programa ve los de cualquiera; los
     * demás, solo los suyos (para saber qué días le toca).
     */
    public function dias(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'mes' => ['nullable', 'date_format:Y-m'],
        ]);

        $userId = (int) ($datos['user_id'] ?? $request->user()->id);

        abort_unless(
            $userId === (int) $request->user()->id || $request->user()->can('asistencia.programar'),
            403
        );

        $mes = $this->mes($datos['mes'] ?? null);
        $servicio = app(DiasDeTrabajo::class);

        return response()->json([
            'data' => [
                'user_id' => $userId,
                'mes' => $mes->format('Y-m'),
                'fechas' => $servicio->delMes($userId, $mes->year, $mes->month),
                // Para el botón «como el mes anterior».
                'como_mes_anterior' => $servicio->comoElMesAnterior($userId, $mes->year, $mes->month),
            ],
        ]);
    }

    /** Guarda el calendario del mes entero (lo no enviado se borra). */
    public function guardarDias(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'mes' => ['required', 'date_format:Y-m'],
            'fechas' => ['present', 'array', 'max:31'],
            'fechas.*' => ['date_format:Y-m-d'],
        ]);

        $mes = $this->mes($datos['mes']);

        try {
            $fechas = app(DiasDeTrabajo::class)->guardar(
                User::findOrFail($datos['user_id']),
                $mes->year,
                $mes->month,
                $datos['fechas'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['fechas' => $e->getMessage()]);
        }

        return response()->json([
            'data' => ['user_id' => (int) $datos['user_id'], 'mes' => $mes->format('Y-m'), 'fechas' => $fechas],
            'mensaje' => count($fechas) === 1 ? '1 día de trabajo guardado.' : count($fechas).' días de trabajo guardados.',
        ]);
    }

    private function mes(?string $mes): Carbon
    {
        return $mes === null ? now()->startOfMonth() : Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
    }
}
