<?php

namespace App\Support;

use App\Models\Caja;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Abrir y cerrar el turno de caja, y cuadrar el efectivo.
 *
 * La gracia del arqueo es comparar **dos números calculados por caminos
 * distintos**: lo que se contó a mano en el cajón y lo que dicen las ventas.
 * Si el sistema dedujera el declarado, no se estaría cuadrando nada.
 */
class ArqueoDeCaja
{
    /**
     * Abre un turno.
     *
     * @param  float|string  $montoInicial  El cambio que se deja en el cajón.
     */
    public function abrir(int $userId, float|string $montoInicial = 0, ?string $notas = null): Caja
    {
        $inicial = ProrrateoDeGastos::aCentavos($montoInicial);

        if ($inicial < 0) {
            throw new RuntimeException('El fondo inicial no puede ser negativo.');
        }

        return DB::transaction(function () use ($userId, $inicial, $notas): Caja {
            // Se comprueba DENTRO de la transacción y con bloqueo: dos pestañas
            // abriendo a la vez dejarían dos cajas abiertas, y a partir de ahí
            // ninguna cuadraría.
            $abierta = Caja::query()->abiertas()->lockForUpdate()->first();

            if ($abierta !== null) {
                throw new RuntimeException(
                    'Ya hay una caja abierta. Ciérrala antes de abrir otra.'
                );
            }

            return Caja::create([
                'abierta_por' => $userId,
                'abierta_en' => now(),
                'monto_inicial' => ProrrateoDeGastos::aDecimal($inicial),
                'estado' => 'abierta',
                'notas' => $notas !== null && trim($notas) !== '' ? trim($notas) : null,
            ]);
        });
    }

    /**
     * Cierra el turno contando lo que hay.
     *
     * @param  float|string  $montoDeclarado  Lo que se contó de verdad.
     */
    public function cerrar(Caja $caja, int $userId, float|string $montoDeclarado, ?string $notas = null): Caja
    {
        if (! $caja->esta_abierta) {
            throw new RuntimeException('Esa caja ya estaba cerrada.');
        }

        $declarado = ProrrateoDeGastos::aCentavos($montoDeclarado);

        if ($declarado < 0) {
            throw new RuntimeException('Lo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($caja, $userId, $declarado, $notas): Caja {
            $esperado = $this->esperadoEnCentavos($caja);

            $caja->update([
                'cerrada_por' => $userId,
                'cerrada_en' => now(),
                'monto_declarado' => ProrrateoDeGastos::aDecimal($declarado),
                'monto_esperado' => ProrrateoDeGastos::aDecimal($esperado),
                // Positivo sobra, negativo falta. Se guarda calculado: si
                // mañana se anula una venta del turno, el arqueo tiene que
                // seguir diciendo lo que se vio esta noche.
                'diferencia' => ProrrateoDeGastos::aDecimal($declarado - $esperado),
                'estado' => 'cerrada',
                'notas' => $notas !== null && trim($notas) !== ''
                    ? trim($notas)
                    : $caja->notas,
            ]);

            return $caja->refresh();
        });
    }

    /** La caja abierta ahora mismo, o null. */
    public function abierta(): ?Caja
    {
        return Caja::query()->abiertas()->latest('abierta_en')->first();
    }

    /**
     * Lo que debería haber en el cajón: el fondo más el efectivo cobrado.
     */
    public function esperadoEnCentavos(Caja $caja): int
    {
        return ProrrateoDeGastos::aCentavos($caja->monto_inicial)
            + $this->efectivoCobradoEnCentavos($caja);
    }

    /**
     * Efectivo que entró al cajón durante el turno.
     *
     * **No se usa `monto_efectivo` a secas**, y esa es la parte delicada. Las
     * ventas con método `tarjeta` o `transferencia` —retiradas del mostrador
     * pero vivas en el histórico— guardan el total en `monto_efectivo`, porque
     * la lógica de reparto solo separa el dinero cuando hay QR de por medio.
     * Sumarlas daría un esperado más alto que lo que hay en el cajón y un
     * faltante inventado cada vez.
     *
     * Solo cuenta lo que de verdad se cobró en billetes:
     *
     *   · `efectivo` → el total
     *   · `mixto`    → solo su parte en efectivo
     *   · el resto   → nada, se cobró fuera de caja
     */
    public function efectivoCobradoEnCentavos(Caja $caja): int
    {
        $ventas = $caja->ventas()
            ->completadas()
            ->get(['metodo_pago', 'total', 'monto_efectivo']);

        return $ventas->reduce(function (int $suma, Venta $venta): int {
            return $suma + match ($venta->metodo_pago) {
                'efectivo' => ProrrateoDeGastos::aCentavos($venta->total),
                'mixto' => ProrrateoDeGastos::aCentavos($venta->monto_efectivo),
                default => 0,
            };
        }, 0);
    }

    /**
     * Ventas en efectivo del horario del turno que NO quedaron atadas a él.
     *
     * Pasa cuando se vendió sin caja abierta. El cierre lo enseña en vez de
     * sumarlas por su cuenta: un arqueo que se inventa de dónde salió el dinero
     * deja de servir para detectar faltantes.
     */
    public function ventasSueltas(Caja $caja): int
    {
        $hasta = $caja->cerrada_en ?? now();

        return Venta::query()
            ->completadas()
            ->whereNull('caja_id')
            ->whereIn('metodo_pago', ['efectivo', 'mixto'])
            ->whereBetween('vendida_en', [$caja->abierta_en, $hasta])
            ->count();
    }
}
