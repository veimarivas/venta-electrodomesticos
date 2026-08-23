<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QrCobro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * QR de cobro: los códigos del banco que se le enseñan al cliente para que
 * pague desde su teléfono.
 *
 * Mismas reglas que el panel (`App\Livewire\QrsCobro\Index`).
 *
 * Va aparte de `PosController::qrs`, que sigue existiendo y devuelve **solo los
 * vigentes** porque es lo que el mostrador puede usar ahora mismo. Aquí se ven
 * todos, incluidos los caducados, que es lo que hace falta para administrarlos.
 */
class QrCobroController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['nullable', 'in:vigentes,caducados,todos'],
        ]);

        $estado = $datos['estado'] ?? 'todos';

        $qrs = QrCobro::query()
            ->when($estado === 'vigentes', fn ($q) => $q->vigentes())
            // Caducado es lo contrario de vigente, y vigente exige activo Y con
            // fecha por delante: se niega la condición entera, no solo la fecha.
            ->when($estado === 'caducados', fn ($q) => $q->whereNot(
                fn ($sub) => $sub->vigentes()
            ))
            ->orderByDesc('activo')
            ->orderBy('fecha_limite')
            ->get();

        return response()->json([
            'data' => $qrs->map(fn (QrCobro $qr): array => $this->aJson($qr))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, null);

        $qr = QrCobro::create($datos);

        return response()->json(['data' => $this->aJson($qr)], 201);
    }

    public function update(Request $request, QrCobro $qr): JsonResponse
    {
        $imagenAnterior = $qr->imagen;
        $datos = $this->validar($request, $qr);

        $qr->update($datos);

        // El archivo anterior se borra DESPUÉS de guardar: al revés, si la
        // escritura falla, el QR se queda sin imagen y sin archivo.
        if (array_key_exists('imagen', $datos) && $imagenAnterior && $datos['imagen'] !== $imagenAnterior) {
            Storage::disk('public')->delete($imagenAnterior);
        }

        return response()->json(['data' => $this->aJson($qr->fresh())]);
    }

    /**
     * Archiva el QR. **No borra su imagen.**
     *
     * Las ventas cobradas con él guardan el respaldo del pago y siguen
     * apuntando a este registro; el histórico tiene que poder mostrarlo.
     */
    public function destroy(QrCobro $qr): JsonResponse
    {
        $qr->delete();

        return response()->json([
            'mensaje' => 'QR archivado. Las ventas que lo usaron conservan su respaldo.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?QrCobro $qr): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'titular' => ['nullable', 'string', 'max:150'],
            // Obligatoria solo al crear: al editar, no subir nada significa
            // conservar la que ya tiene.
            'imagen' => [
                $qr === null ? 'required' : 'nullable',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:3072',
            ],
            // Se admite registrar uno ya caducado —queda archivado—, pero el
            // POS no lo ofrece: la vigencia la decide `scopeVigentes`.
            'fecha_limite' => ['required', 'date', 'after_or_equal:2020-01-01'],
            'activo' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $guardar = [
            'nombre' => trim($datos['nombre']),
            'fecha_limite' => $datos['fecha_limite'],
            'activo' => $datos['activo'] ?? $qr?->activo ?? true,
        ];

        foreach (['banco', 'titular', 'notas'] as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));

            $guardar[$campo] = $valor === '' ? null : $valor;
        }

        if ($request->hasFile('imagen')) {
            $guardar['imagen'] = $request->file('imagen')->store('qrs-cobro', 'public');
        }

        return $guardar;
    }

    /**
     * @return array<string, mixed>
     */
    private function aJson(QrCobro $qr): array
    {
        return [
            'id' => $qr->id,
            'nombre' => $qr->nombre,
            'banco' => $qr->banco,
            'titular' => $qr->titular,
            'imagen_url' => $qr->imagen_url,
            'fecha_limite' => $qr->fecha_limite?->toDateString(),
            'dias_restantes' => $qr->dias_restantes,
            'activo' => (bool) $qr->activo,
            // Lo que de verdad decide si el POS lo ofrece: activo Y sin
            // caducar, la misma condición de `scopeVigentes`. Con los dos
            // campos sueltos, la app tendría que recalcular una regla que ya
            // vive en el modelo, y el día de la fecha límite todavía cuenta.
            'vigente' => (bool) $qr->activo && $qr->dias_restantes >= 0,
            'notas' => $qr->notas,
        ];
    }
}
