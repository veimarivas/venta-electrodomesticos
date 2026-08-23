<?php

/*
|--------------------------------------------------------------------------
| Configuración de la plantilla Velzon
|--------------------------------------------------------------------------
|
| Estos valores se escriben como atributos data-* en la etiqueta <html> y
| son los que lee assets/js/layout.js para pintar el tema. Centralizarlos
| aquí evita tener que editar el layout Blade para cambiar la apariencia.
|
*/

return [

    // vertical | horizontal | twocolumn | semibox
    'layout' => env('VELZON_LAYOUT', 'vertical'),

    // light | dark
    'topbar' => env('VELZON_TOPBAR', 'light'),

    // light | dark | gradient
    'sidebar' => env('VELZON_SIDEBAR', 'dark'),

    // lg | md | sm | sm-hover
    'sidebar_size' => env('VELZON_SIDEBAR_SIZE', 'lg'),

    // none | img-1 ... img-4
    'sidebar_image' => env('VELZON_SIDEBAR_IMAGE', 'none'),

    // enable | disable
    'preloader' => env('VELZON_PRELOADER', 'disable'),

    // light | dark  (modo de color de Bootstrap 5.3)
    'mode' => env('VELZON_MODE', 'light'),

    // default | saas | corporate | galaxy | material | creative | minimal | modern | interactive
    'theme' => env('VELZON_THEME', 'default'),

    // default | green | purple | blue | orange
    'theme_colors' => env('VELZON_THEME_COLORS', 'default'),

    // Mostrar el panel flotante de personalización (útil en desarrollo)
    'show_customizer' => env('VELZON_CUSTOMIZER', false),

];
