<?php

namespace App\Support;

use App\Models\MovimientoInventario;
use App\Models\Unidad;
use Illuminate\Database\Eloquent\Model;

/**
 * Punto único por el que se escribe el kardex.
 *
 * Todo lo que le pasa a una unidad física pasa por aquí: la entrada al
 * recepcionar una compra, el alta manual de regularización y cualquier cambio
 * de estado posterior. Concentrarlo evita que un flujo nuevo mueva el
 * inventario y se olvide de dejar rastro, que es justo lo que haría inútil la
 * auditoría.
 */
class Kardex
{
    /**
     * Qué tipo de movimiento representa cada estado de destino.
     *
     * Un aparato que pasa a 'vendido' es una salida; a 'devuelto', una
     * devolución; a 'danado', un daño. El resto son ajustes de estado.
     */
    private const TIPO_POR_ESTADO = [
        'vendido' => 'salida',
        'perdido' => 'salida',
        'devuelto' => 'devolucion',
        'danado' => 'dano',
    ];

    /**
     * Registra la entrada de una unidad al almacén: es su primer movimiento.
     *
     * @param  Model|null  $origen  La compra que la trajo, si viene de una.
     */
    public function entrada(Unidad $unidad, ?Model $origen = null, ?string $notas = null): MovimientoInventario
    {
        return $this->registrar(
            unidad: $unidad,
            tipo: 'entrada',
            estadoAnterior: null,
            estadoNuevo: $unidad->estado,
            origen: $origen,
            notas: $notas,
        );
    }

    /**
     * Registra un cambio de estado. Devuelve null si el estado no cambió: un
     * kardex lleno de filas que no mueven nada no se puede leer.
     */
    public function cambioDeEstado(
        Unidad $unidad,
        string $estadoAnterior,
        ?Model $origen = null,
        ?string $notas = null,
    ): ?MovimientoInventario {
        if ($estadoAnterior === $unidad->estado) {
            return null;
        }

        return $this->registrar(
            unidad: $unidad,
            tipo: self::TIPO_POR_ESTADO[$unidad->estado] ?? 'ajuste',
            estadoAnterior: $estadoAnterior,
            estadoNuevo: $unidad->estado,
            origen: $origen,
            notas: $notas,
        );
    }

    /**
     * Escritura cruda. Privada a propósito: los flujos usan los métodos de
     * arriba, que ya deciden el tipo correcto.
     */
    private function registrar(
        Unidad $unidad,
        string $tipo,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?Model $origen,
        ?string $notas,
    ): MovimientoInventario {
        return MovimientoInventario::create([
            'unidad_id' => $unidad->id,
            'tipo' => $tipo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'origen_type' => $origen?->getMorphClass(),
            'origen_id' => $origen?->getKey(),
            // auth()->id() es null en seeders y comandos de consola: el
            // movimiento se registra igual, sin autor.
            'user_id' => auth()->id(),
            'cantidad' => 1,
            'notas' => $notas !== null && trim($notas) !== '' ? trim($notas) : null,
        ]);
    }
}
