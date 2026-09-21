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

/**
 * Bandeja de autorizaciones de descuento.
 *
 * Solo la escucha quien puede resolverlas. El payload trae el costo del
 * aparato, que no es información para todo el personal.
 */
Broadcast::channel('autorizaciones', function ($user) {
    return $user->can('ventas.autorizar_descuento');
});

/**
 * Disponibilidad del inventario.
 *
 * Lo escuchan las pantallas que muestran cuántas unidades quedan (catálogo,
 * stock y unidades). No lleva datos, solo avisa de que hay que refrescar, así
 * que basta con poder ver el catálogo o el inventario.
 */
Broadcast::channel('inventario', function ($user) {
    return $user->can('productos.ver')
        || $user->can('inventario.ver')
        || $user->can('unidades.ver')
        || $user->can('stock.ver');
});

/**
 * Canal de una solicitud concreta: ahí viaja su resolución.
 *
 * Lo escuchan el vendedor que la pidió —para que su carrito se actualice solo—
 * y quien puede autorizar. Nadie más.
 */
Broadcast::channel('solicitud.{solicitudId}', function ($user, int $solicitudId) {
    $solicitud = \App\Models\SolicitudDescuento::find($solicitudId);

    if ($solicitud === null) {
        return false;
    }

    return (int) $user->id === (int) $solicitud->user_id
        || $user->can('ventas.autorizar_descuento');
});

