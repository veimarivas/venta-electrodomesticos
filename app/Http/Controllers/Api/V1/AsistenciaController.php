<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Tienda;
use App\Models\User;
use App\Support\RegistroDeAsistencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Asistencia desde el teléfono: marcar entrada y salida dentro de la tienda y
 * el historial del mes. El servidor vuelve a medir la distancia: ver
 * `RegistroDeAsistencia`.
 */
class AsistenciaController extends Controller
{
    public function __construct(private readonly RegistroDeAsistencia $registro) {}

    /** El turno abierto, los de hoy y las tiendas con su ubicación y radio. */
    public function estado(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $hoy = Asistencia::query()
            ->with('tienda:id,nombre')
            ->where('user_id', $usuario->id)
            ->whereDate('fecha', today())
            ->orderBy('entrada_en')
            ->get();

        return response()->json([
            'abierta' => $this->registro->abierta($usuario)?->aArreglo(),
            'hoy' => $hoy->map(fn (Asistencia $a): array => $a->aArreglo())->values(),
            'tiendas' => Tienda::query()->paraMarcar()->orderBy('nombre')->get()
                ->map(fn (Tienda $t): array => self::tienda($t))->values(),
            'precision_maxima' => RegistroDeAsistencia::PRECISION_MAXIMA,
            'puede_ver_todos' => $usuario->can('asistencia.ver'),
            'puede_fijar_tiendas' => $usuario->can('tiendas.editar'),
        ]);
    }

    public function entrada(Request $request): JsonResponse
    {
        return $this->marcar($request, 'entrada');
    }

    public function salida(Request $request): JsonResponse
    {
        return $this->marcar($request, 'salida');
    }

    private function marcar(Request $request, string $que): JsonResponse
    {
        $datos = $request->validate([
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'numeric', 'min:0'],
            'simulada' => ['nullable', 'boolean'],
        ]);

        $argumentos = [
            $request->user(),
            (float) $datos['latitud'],
            (float) $datos['longitud'],
            isset($datos['precision']) ? (float) $datos['precision'] : null,
            (bool) ($datos['simulada'] ?? false),
        ];

        try {
            $asistencia = $que === 'entrada'
                ? $this->registro->marcarEntrada(...$argumentos)
                : $this->registro->marcarSalida(...$argumentos);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['ubicacion' => [$e->getMessage()]],
            ], 422);
        }

        $hora = $que === 'entrada' ? $asistencia->entrada_en : $asistencia->salida_en;
        $mensaje = $que === 'entrada'
            ? "Entrada marcada a las {$hora->format('H:i')} en {$asistencia->tienda->nombre}."
            : "Salida marcada a las {$hora->format('H:i')}.";

        if ($que === 'entrada' && $asistencia->llegoTarde()) {
            $mensaje .= " Llegaste {$asistencia->minutos_atraso} min tarde.";
        }

        return response()->json([
            'mensaje' => $mensaje,
            'asistencia' => $asistencia->aArreglo(),
        ], $que === 'entrada' ? 201 : 200);
    }

    /**
     * Historial de un mes (`mes=2026-10`). Cada uno ve el suyo; con
     * `asistencia.ver`, el de todos o el de uno (`user_id`).
     */
    public function historial(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer'],
            'tienda_id' => ['nullable', 'integer'],
        ]);

        $usuario = $request->user();
        $verTodos = $usuario->can('asistencia.ver');
        $mes = Carbon::createFromFormat('Y-m', $datos['mes'] ?? now()->format('Y-m'))->startOfMonth();

        $userId = $verTodos ? ($datos['user_id'] ?? null) : $usuario->id;

        return response()->json([
            'data' => $this->registro->historial(
                (int) $mes->year,
                (int) $mes->month,
                $userId === null ? null : (int) $userId,
                isset($datos['tienda_id']) ? (int) $datos['tienda_id'] : null,
            ),
            'meta' => [
                'mes' => $mes->format('Y-m'),
                'todos' => $verTodos && $userId === null,
                'trabajadores' => $verTodos
                    ? User::permission('asistencia.marcar')->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                        ->map(fn (User $u): array => ['id' => $u->id, 'nombre' => $u->name])->values()
                    : [],
                'tiendas' => Tienda::query()->orderBy('nombre')->get(['id', 'nombre'])
                    ->map(fn (Tienda $t): array => ['id' => $t->id, 'nombre' => $t->nombre])->values(),
            ],
        ]);
    }

    /** El administrador pone la salida olvidada (`salida` = HH:MM del mismo día). */
    public function corregir(Request $request, Asistencia $asistencia): JsonResponse
    {
        $datos = $request->validate([
            'salida' => ['required', 'date_format:H:i'],
            'motivo' => ['required', 'string', 'min:4', 'max:255'],
        ], [
            'motivo.required' => 'Indica por qué se corrige.',
        ]);

        try {
            $corregida = $this->registro->corregirSalida(
                $asistencia,
                $asistencia->fecha->copy()->setTimeFromTimeString($datos['salida']),
                $request->user(),
                $datos['motivo'],
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['salida' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'mensaje' => 'Salida corregida.',
            'asistencia' => $corregida->aArreglo(),
        ]);
    }

    public static function tienda(Tienda $t): array
    {
        return [
            'id' => $t->id,
            'nombre' => $t->nombre,
            'direccion' => $t->direccion,
            'latitud' => $t->latitud,
            'longitud' => $t->longitud,
            'radio_metros' => $t->radio_metros,
            'hora_entrada' => $t->horaEntradaCorta(),
            'tolerancia_minutos' => $t->tolerancia_minutos,
            'activa' => $t->activa,
        ];
    }
}
