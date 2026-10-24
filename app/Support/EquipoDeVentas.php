<?php

namespace App\Support;

use App\Models\Trabajador;

/**
 * El personal en un solo lugar: cada trabajador activo con su cuenta de
 * acceso (o la falta de ella) y los días que tiene programados en el mes.
 *
 * Es lo que ve el administrador en «Vendedores» (panel y app) para crear la
 * cuenta de quien entra, reiniciar el acceso de quien lo olvidó y armar el
 * calendario del mes.
 */
class EquipoDeVentas
{
    /** @return list<array<string, mixed>> */
    public function listado(int $anio, int $mes, ?string $buscar = null): array
    {
        $dias = app(DiasDeTrabajo::class)->cantidadesDelMes($anio, $mes);

        return Trabajador::query()
            ->activos()
            ->with(['persona.user.roles', 'cargo'])
            ->buscar($buscar)
            ->get()
            ->sortBy(fn (Trabajador $t): string => mb_strtolower((string) $t->persona?->nombre_completo))
            ->map(function (Trabajador $t) use ($dias): array {
                $cuenta = $t->persona?->user;

                return [
                    'trabajador_id' => $t->id,
                    'codigo' => $t->codigo,
                    'nombre' => $t->persona?->nombre_completo ?? '—',
                    'iniciales' => $t->persona?->iniciales,
                    'cargo' => $t->cargo?->nombre,
                    'celular' => $t->persona?->celular,
                    'cuenta' => $cuenta === null ? null : [
                        'id' => $cuenta->id,
                        'usuario' => $cuenta->name,
                        'activa' => (bool) $cuenta->is_active,
                        'roles' => $cuenta->getRoleNames()->all(),
                        'debe_cambiar_password' => (bool) $cuenta->debe_cambiar_password,
                        'ultimo_acceso' => $cuenta->last_login_at?->toIso8601String(),
                    ],
                    'dias_programados' => $cuenta === null ? 0 : ($dias[$cuenta->id] ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
