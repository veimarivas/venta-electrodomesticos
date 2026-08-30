<?php

namespace App\Console\Commands;

use App\Models\Cuota;
use App\Models\User;
use App\Notifications\CuotaPorCobrarPush;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa de las cuotas que vencen hoy y de las que acaban de vencerse.
 *
 * **No hay columna de «ya avisado», y es deliberado.** El aviso se dispara por
 * dos fechas exactas —el día del vencimiento y el día siguiente—, así que cada
 * cuota genera como mucho dos avisos aunque el comando se ejecute varias veces
 * al día: el segundo se manda solo si la cuota sigue sin pagarse. Una marca en
 * la base habría que mantenerla al corregir un pago, y bastaría olvidarse una
 * vez para que una cuota dejara de avisar para siempre.
 *
 * Lo dispara `schedule:work`. Sin ese proceso corriendo no sale ningún aviso
 * (ver docs/DESPLIEGUE.md §4).
 */
class AvisarCuotasPorCobrar extends Command
{
    protected $signature = 'cuotas:avisar';

    protected $description = 'Avisa de las cuotas que vencen hoy y de las que acaban de vencerse';

    public function handle(): int
    {
        $destinatarios = $this->destinatarios();

        if ($destinatarios->isEmpty()) {
            $this->info('Nadie con permiso de ver créditos: no hay a quién avisar.');

            return self::SUCCESS;
        }

        $hoy = $this->cuotas(fn (Builder $q) => $q->whereDate('vence_en', today()));
        $vencidas = $this->cuotas(fn (Builder $q) => $q->whereDate('vence_en', today()->subDay()));

        foreach ($hoy as $cuota) {
            Notification::send($destinatarios, new CuotaPorCobrarPush($cuota, 'hoy'));
        }

        foreach ($vencidas as $cuota) {
            Notification::send($destinatarios, new CuotaPorCobrarPush($cuota, 'vencida'));
        }

        $this->info(
            $hoy->count().' '.($hoy->count() === 1 ? 'cuota vence' : 'cuotas vencen').' hoy · '.
            $vencidas->count().' '.($vencidas->count() === 1 ? 'cuota se venció' : 'cuotas se vencieron').' ayer.'
        );

        return self::SUCCESS;
    }

    /**
     * Cuotas pendientes de créditos vivos que cumplen la condición de fecha.
     *
     * @param  callable(Builder): Builder  $acotar
     * @return Collection<int, Cuota>
     */
    private function cuotas(callable $acotar): Collection
    {
        return $acotar(
            Cuota::query()
                ->with('credito.cliente.persona')
                ->pendientes()
                // Un crédito anulado no se cobra: avisar de sus cuotas mandaría
                // a alguien a reclamar una deuda que ya no existe.
                ->whereHas('credito', fn (Builder $c) => $c->vigentes())
        )->get();
    }

    /**
     * Quien cobra, no quien vende. El aviso lleva el nombre del cliente y lo
     * que debe: es información de cartera, y va a quien puede hacer algo con
     * ella.
     *
     * @return Collection<int, User>
     */
    private function destinatarios(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u): bool => $u->can('creditos.ver'))
            ->values();
    }
}
