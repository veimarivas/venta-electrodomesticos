<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnidadResource;
use App\Models\Unidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Unidades físicas del almacén, desde el teléfono.
 *
 * Existe por una sola tarea: **registrar el serial del fabricante leyéndolo con
 * la cámara**. El código interno lo pone el sistema al recepcionar la compra,
 * pero el serial viene impreso en la caja o en la parte de atrás del aparato, y
 * teclear doce caracteres alfanuméricos con el aparato en la mano es justo lo
 * que la cámara hace en un segundo.
 *
 * El resto de la edición de unidades (precio, ubicación, estado) se queda en el
 * panel web: son campos que se revisan con calma, no de pie en el almacén.
 */
class UnidadController extends Controller
{
    /**
     * Guarda el serial leído por la cámara.
     *
     * El serial es único en toda la tabla, así que un duplicado no es un error
     * técnico que haya que esconder: casi siempre significa que **este aparato
     * ya se registró antes**, o que se está escaneando el código de barras
     * equivocado. Se responde con un 422 y un mensaje que dice cuál es la otra
     * unidad, que es lo que el almacenero necesita para resolverlo.
     */
    public function registrarSerial(Request $request, Unidad $unidad): JsonResponse
    {
        $datos = $request->validate([
            'serial' => ['required', 'string', 'max:100'],
        ]);

        $serial = trim($datos['serial']);

        // `trim` puede dejarlo vacío aunque `required` haya pasado (un serial
        // de solo espacios). Vacío tiene que ser NULL, nunca cadena vacía: el
        // índice único rechazaría la segunda unidad sin serial.
        if ($serial === '') {
            throw ValidationException::withMessages([
                'serial' => 'El serial no puede estar en blanco.',
            ]);
        }

        // El duplicado se comprueba a mano y no con `Rule::unique`, por dos
        // razones: se mira DESPUÉS del trim («ABC123 » y «ABC123» son el mismo
        // serial) y sin distinguir mayúsculas, y así el mensaje puede decir en
        // qué unidad está ya ese serial en vez de un «ya está registrado» que
        // deja al almacenero sin saber dónde buscar.
        $ocupado = Unidad::query()
            ->whereKeyNot($unidad->id)
            ->whereRaw('LOWER(serial) = ?', [mb_strtolower($serial)])
            ->first();

        if ($ocupado !== null) {
            throw ValidationException::withMessages([
                'serial' => "Ese serial ya está registrado en la unidad {$ocupado->codigo_interno}.",
            ]);
        }

        $unidad->update(['serial' => $serial]);

        return response()->json([
            'mensaje' => 'Serial registrado.',
            'data' => new UnidadResource($unidad->fresh()),
        ]);
    }
}
