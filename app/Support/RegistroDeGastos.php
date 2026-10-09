<?php

namespace App\Support;

use App\Models\Gasto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Alta, corrección y archivo de gastos. El panel y la API pasan por aquí para
 * que la regla del cajón sea la misma en los dos.
 *
 * Un gasto **en efectivo de hoy** puede salir del cajón del turno abierto
 * (`de_caja`): entonces queda atado a ese turno y el arqueo lo resta del
 * esperado. Los pagados por QR o transferencia —la mayoría— no tocan el
 * cajón; entran solo en el resumen del día.
 */
class RegistroDeGastos
{
    public function __construct(private readonly ArqueoDeCaja $arqueo) {}

    /**
     * @param  array{fecha: string, concepto: string, categoria: string, monto: string|float, metodo_pago: string, beneficiario_id?: ?int, notas?: ?string, de_caja?: bool}  $datos
     */
    public function guardar(array $datos, int $userId, ?Gasto $gasto = null, ?UploadedFile $comprobante = null): Gasto
    {
        if (! array_key_exists($datos['categoria'], Gasto::CATEGORIAS)) {
            throw new RuntimeException('Categoría de gasto desconocida.');
        }

        if (! array_key_exists($datos['metodo_pago'], Gasto::METODOS)) {
            throw new RuntimeException('Método de pago desconocido.');
        }

        $centavos = ProrrateoDeGastos::aCentavos($datos['monto']);

        if ($centavos <= 0) {
            throw new RuntimeException('El monto tiene que ser mayor que cero.');
        }

        $cajaId = $this->cajaDelGasto($datos, $centavos, $gasto);

        $valores = [
            'fecha' => $datos['fecha'],
            'concepto' => trim($datos['concepto']),
            'categoria' => $datos['categoria'],
            'monto' => ProrrateoDeGastos::aDecimal($centavos),
            'metodo_pago' => $datos['metodo_pago'],
            'beneficiario_id' => $datos['beneficiario_id'] ?? null,
            'caja_id' => $cajaId,
            'notas' => isset($datos['notas']) && trim((string) $datos['notas']) !== '' ? trim($datos['notas']) : null,
        ];

        if ($comprobante !== null) {
            if ($gasto?->comprobante) {
                Storage::disk('public')->delete($gasto->comprobante);
            }

            $valores['comprobante'] = $comprobante->store('comprobantes-gasto', 'public');
        }

        if ($gasto === null) {
            return Gasto::create([...$valores, 'user_id' => $userId]);
        }

        $gasto->update($valores);

        return $gasto->refresh();
    }

    /**
     * Archiva el gasto (borrado suave): el resumen de ese día deja de contarlo.
     * Si ya entró en un cierre de caja, ese cierre no se mueve: guarda su foto.
     */
    public function archivar(Gasto $gasto): void
    {
        $gasto->delete();
    }

    /**
     * Turno al que se ata el gasto, o null. Solo un gasto en efectivo, de hoy
     * y marcado «sale del cajón» con un turno abierto; y no puede sacar más de
     * lo que hay.
     */
    private function cajaDelGasto(array $datos, int $centavos, ?Gasto $gasto): ?int
    {
        $sale = (bool) ($datos['de_caja'] ?? false)
            && $datos['metodo_pago'] === 'efectivo'
            && now()->isSameDay(\Illuminate\Support\Carbon::parse($datos['fecha']));

        if (! $sale) {
            return null;
        }

        // Si ya estaba atado a un turno (una corrección), se queda en ese.
        if ($gasto?->caja_id !== null) {
            return $gasto->caja_id;
        }

        $caja = $this->arqueo->abierta();

        if ($caja === null) {
            throw new RuntimeException('No hay una caja abierta de la que sacar el efectivo. Desmarca «sale del cajón» o abre el turno.');
        }

        if ($centavos > $this->arqueo->esperadoEnCentavos($caja)) {
            throw new RuntimeException('En el cajón no hay tanto efectivo para este gasto.');
        }

        return $caja->id;
    }
}
