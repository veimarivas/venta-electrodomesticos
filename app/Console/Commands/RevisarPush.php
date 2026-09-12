<?php

namespace App\Console\Commands;

use App\Models\Dispositivo;
use Illuminate\Console\Command;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Dice si el push está listo para funcionar.
 *
 * Existe porque la falta de credenciales es invisible: los avisos se guardan y
 * se leen por API igual, así que nada se rompe y nadie nota que al teléfono no
 * llega nada. Este comando contesta las dos preguntas que lo deciden —¿está el
 * paquete? ¿hay credenciales y teléfonos registrados?— en una sola línea.
 */
class RevisarPush extends Command
{
    protected $signature = 'push:revisar';

    protected $description = 'Comprueba si el envío de notificaciones push está configurado';

    public function handle(): int
    {
        $paquete = class_exists(FcmChannel::class);
        $credenciales = filled(config('services.fcm.credentials'));
        $projectId = filled(config('services.fcm.project_id'));
        $dispositivos = Dispositivo::query()->count();

        $this->line('Paquete laravel-notification-channels/fcm: '.($paquete ? 'instalado' : 'FALTA'));
        $this->line('Credenciales FCM: '.($credenciales ? 'configuradas' : 'FALTAN'));
        $this->line('Proyecto de Firebase: '.($projectId ? 'indicado' : 'FALTA'));
        $this->line("Teléfonos registrados: {$dispositivos}");

        if ($paquete && $credenciales && $dispositivos > 0) {
            $this->info('Push listo: los avisos saldrán al teléfono.');

            return self::SUCCESS;
        }

        $this->warn(
            'Push APAGADO. Los avisos se siguen guardando y se leen por API; '
            .'lo único que falta es que lleguen al teléfono.'
        );

        if (! $credenciales) {
            $this->line('Pon FIREBASE_CREDENTIALS (ruta al service-account.json) en el .env.');
        }

        if ($dispositivos === 0) {
            $this->line('Ningún teléfono registrado todavía: hay que entrar desde la app.');
        }

        return self::SUCCESS;
    }
}
