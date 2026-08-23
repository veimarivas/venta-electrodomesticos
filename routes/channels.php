<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Canal del dashboard en vivo.
 *
 * Solo lo escuchan quienes pueden ver los reportes: el payload lleva importes
 * y ganancias, y un vendedor no tiene por qué ver el resultado global de la
 * tienda en tiempo real.
 */
Broadcast::channel('ventas', function ($user) {
    return $user->can('reportes.ver');
});
