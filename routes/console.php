<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Copia de seguridad diaria
|--------------------------------------------------------------------------
|
| Requiere que en el servidor corra `php artisan schedule:work` (ver el
| manual de despliegue). Sin ese proceso vivo, estas tres líneas no se
| ejecutan nunca y la tienda opera sin copias creyendo que las tiene.
|
| El orden importa: primero se limpia lo viejo y después se hace la copia
| nueva. Al revés, la limpieza podría borrar la recién creada si el disco ya
| estaba al límite, y el día más reciente es justo el que no se puede perder.
*/

Schedule::command('backup:clean')
    ->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->withoutOverlapping();

/*
| Vigilancia: si la última copia tiene más de un día, avisa por correo. Es la
| única parte que se entera de que el respaldo lleva semanas sin correr —una
| tarea programada que falla en silencio es peor que no tenerla, porque da
| una tranquilidad falsa.
*/
Schedule::command('backup:monitor')
    ->dailyAt('08:00');

/*
|--------------------------------------------------------------------------
| Cuotas por cobrar
|--------------------------------------------------------------------------
|
| A primera hora, antes de abrir: las cuotas que vencen hoy y las que se
| vencieron ayer. La cartera ya se puede consultar en pantalla, pero un
| listado solo sirve a quien se acuerda de abrirlo, y una cuota que nadie
| mira se convierte en mora en silencio.
|
| `withoutOverlapping` por si el envío se alarga con muchos destinatarios:
| dos pasadas a la vez duplicarían cada aviso.
*/
Schedule::command('cuotas:avisar')
    ->dailyAt('08:30')
    ->withoutOverlapping();
