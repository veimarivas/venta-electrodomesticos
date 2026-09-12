<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versión mínima de la app móvil
    |--------------------------------------------------------------------------
    |
    | La app consulta este número al arrancar. Si la versión instalada es menor,
    | avisa que hay que actualizarla antes de seguir: un APK viejo contra un
    | backend nuevo da pantallas rotas y un APK nuevo contra un backend viejo da
    | 404 en las rutas que faltan.
    |
    | Se sube al publicar un APK que use endpoints nuevos. Se puede cambiar sin
    | tocar código con la variable VENTAS_APP_MINIMA del .env.
    |
    */
    'app_minima' => env('VENTAS_APP_MINIMA', '1.0.0'),

];
