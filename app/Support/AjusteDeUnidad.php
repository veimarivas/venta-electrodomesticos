<?php

namespace App\Support;

use App\Models\Unidad;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Punto único por el que se ajusta una unidad ya existente.
 *
 * Existe porque el cambio de estado de un aparato tiene una regla que no se
 * puede olvidar: **deja rastro en el kardex**. El panel la olvidaba —editar una
 * unidad y marcarla como dañada actualizaba la fila y no escribía movimiento
 * alguno—, y al abrir el inventario en el teléfono habría hecho falta repetir
 * esa misma lógica en el controlador de la API. Dos copias de una regla es la
 * forma más segura de que una de las dos se quede atrás.
 *
 * Los flujos que MUEVEN la unidad por su cuenta —la venta, su anulación— no
 * pasan por aquí: tienen su propio servicio (`RegistroDeVenta`) y escriben el
 * kardex con la venta como origen, que es más información de la que este
 * ajuste puede dar.
 */
class AjusteDeUnidad
{
    /**
     * Estados que no se pueden poner ni quitar a mano.
     *
     * `vendido` lo pone la venta y lo quita su anulación. Marcarlo a mano
     * dejaría una unidad fuera del stock sin venta que la respalde, y quitarlo
     * dejaría una línea de venta apuntando a un aparato que vuelve a figurar
     * disponible: el mismo aparato se vendería dos veces.
     */
    private const ESTADOS_DE_LA_VENTA = ['vendido'];

    public function __construct(private readonly Kardex $kardex) {}

    /**
     * Aplica los cambios y registra el movimiento si el estado se movió.
     *
     * @param  array<string, mixed>  $cambios  Claves de la unidad: estado,
     *                                         ubicacion, notas…
     * @param  string|null  $notas  Motivo del ajuste, para el kardex. No es lo
     *                              mismo que `$cambios['notas']`, que es la
     *                              nota permanente de la unidad.
     *
     * @throws RuntimeException si el estado pedido lo gobierna la venta.
     */
    public function aplicar(Unidad $unidad, array $cambios, ?string $notas = null): Unidad
    {
        $estadoAnterior = $unidad->estado;
        $estadoNuevo = $cambios['estado'] ?? $estadoAnterior;

        if ($estadoNuevo !== $estadoAnterior) {
            $this->comprobarTransicion($estadoAnterior, $estadoNuevo);
        }

        // La actualización y su rastro van en la misma transacción: un
        // inventario que se mueve sin historia no se puede auditar, y una
        // historia de algo que no llegó a moverse tampoco sirve.
        return DB::transaction(function () use ($unidad, $cambios, $estadoAnterior, $notas): Unidad {
            $unidad->update($cambios);

            $this->kardex->cambioDeEstado($unidad, $estadoAnterior, notas: $notas);

            return $unidad;
        });
    }

    private function comprobarTransicion(string $desde, string $hacia): void
    {
        if (in_array($hacia, self::ESTADOS_DE_LA_VENTA, true)) {
            throw new RuntimeException(
                'Un aparato se marca como vendido registrando su venta, no editándolo.'
            );
        }

        if (in_array($desde, self::ESTADOS_DE_LA_VENTA, true)) {
            throw new RuntimeException(
                'Este aparato está vendido. Para devolverlo al stock hay que anular su venta.'
            );
        }
    }
}
