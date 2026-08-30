<?php

/*
|--------------------------------------------------------------------------
| Menú lateral del panel
|--------------------------------------------------------------------------
|
| Estructura declarativa del sidebar. Evita tener que tocar el HTML de la
| plantilla cada vez que se agrega un módulo.
|
| Claves admitidas por ítem:
|   type       => 'title' para un encabezado de sección
|   label      => texto visible
|   icon       => clase del icono (Remix / Boxicons / Material Design)
|   route      => nombre de ruta (se resuelve con route())
|   url        => URL literal, alternativa a 'route'
|   active     => patrón(es) para marcar el ítem activo (ej. 'productos*')
|   permission => permiso requerido (spatie/laravel-permission)
|   badge      => ['text' => 'Nuevo', 'class' => 'bg-danger']
|   children   => array de subítems (anidamiento ilimitado)
|
*/

return [

    ['type' => 'title', 'label' => 'Principal'],

    [
        'label' => 'Dashboard',
        'icon' => 'ri-dashboard-2-line',
        'route' => 'dashboard',
        'active' => 'dashboard',
    ],

    [
        'label' => 'Stock Actual',
        'icon' => 'ri-stack-line',
        'route' => 'stock.index',
        'active' => 'stock*',
        'permission' => 'stock.ver',
    ],

    ['type' => 'title', 'label' => 'Catálogo'],

    [
        'label' => 'Productos',
        'icon' => 'ri-archive-drawer-line',
        'active' => ['categorias*', 'marcas*', 'productos*'],
        'permission' => 'productos.ver',
        'children' => [
            ['label' => 'Categorías', 'route' => 'categorias.index', 'active' => 'categorias*', 'permission' => 'categorias.ver'],
            ['label' => 'Marcas', 'route' => 'marcas.index', 'active' => 'marcas*', 'permission' => 'marcas.ver'],
            ['label' => 'Productos', 'route' => 'productos.index', 'active' => 'productos*', 'permission' => 'productos.ver'],
        ],
    ],

    ['type' => 'title', 'label' => 'Operaciones'],

    [
        'label' => 'Ventas',
        'icon' => 'ri-shopping-cart-2-line',
        'active' => ['ventas*', 'clientes*', 'creditos*', 'entregas*'],
        // Sin permiso en el grupo: cada hijo declara el suyo y MenuBuilder
        // descarta el grupo si se queda sin ítems visibles. Con 'ventas.ver'
        // aquí, quien solo puede ver clientes no vería ni la sección.
        'children' => [
            ['label' => 'Punto de venta', 'route' => 'ventas.create', 'active' => 'ventas/nueva', 'permission' => 'ventas.crear'],
            ['label' => 'Historial de ventas', 'route' => 'ventas.index', 'active' => 'ventas', 'permission' => 'ventas.ver'],
            ['label' => 'Créditos y cuotas', 'route' => 'creditos.index', 'active' => 'creditos*', 'permission' => 'creditos.ver'],
            ['label' => 'Entregas', 'route' => 'entregas.index', 'active' => 'entregas*', 'permission' => 'entregas.ver'],
            ['label' => 'Clientes', 'route' => 'clientes.index', 'active' => 'clientes*', 'permission' => 'clientes.ver'],
            ['label' => 'QR de cobro', 'route' => 'ventas.qrs-cobro.index', 'active' => 'ventas/qr-cobro*', 'permission' => 'qrs_cobro.ver'],
            // Con `caja.gestionar` basta: el cajero abre y cierra su turno
            // aunque no pueda repasar el histórico de todos.
            ['label' => 'Caja', 'route' => 'caja.index', 'active' => 'caja*', 'permission' => 'caja.gestionar'],
        ],
    ],

    [
        'label' => 'Compras',
        'icon' => 'ri-truck-line',
        'active' => ['proveedores*', 'compras*'],
        'children' => [
            ['label' => 'Proveedores', 'route' => 'proveedores.index', 'active' => 'proveedores*', 'permission' => 'proveedores.ver'],
            ['label' => 'Órdenes de compra', 'route' => 'compras.index', 'active' => 'compras*', 'permission' => 'compras.ver'],
        ],
    ],

    [
        'label' => 'Inventario',
        'icon' => 'ri-barcode-box-line',
        'active' => 'inventario*',
        'permission' => 'inventario.ver',
        'children' => [
            ['label' => 'Unidades', 'route' => 'inventario.unidades.index', 'active' => 'inventario/unidades*', 'permission' => 'unidades.ver'],
            ['label' => 'Kardex', 'route' => 'inventario.kardex', 'active' => 'inventario/kardex*', 'permission' => 'inventario.ver'],
        ],
    ],

    // Sección propia y no dentro de Ventas: el taller es su propio trabajo, con
    // su tablero y su técnico, y por aquí pasan también aparatos que la tienda
    // nunca vendió —los que llegaron fallados del proveedor—.
    [
        'label' => 'Servicio técnico',
        'icon' => 'ri-tools-line',
        'route' => 'reparaciones.index',
        'active' => 'reparaciones*',
        'permission' => 'reparaciones.ver',
    ],

    ['type' => 'title', 'label' => 'Personal'],

    [
        'label' => 'Personas',
        'icon' => 'ri-team-line',
        'route' => 'personas.index',
        'active' => 'personas*',
        'permission' => 'personas.ver',
    ],

    [
        'label' => 'Trabajadores',
        'icon' => 'ri-user-star-line',
        'route' => 'trabajadores.index',
        'active' => 'trabajadores*',
        'permission' => 'trabajadores.ver',
    ],

    [
        'label' => 'Cargos',
        'icon' => 'ri-briefcase-line',
        'route' => 'cargos.index',
        'active' => 'cargos*',
        'permission' => 'cargos.ver',
    ],

    ['type' => 'title', 'label' => 'Análisis'],

    // Un solo ítem, no tres: la pantalla de reportes reúne en una vista lo que
    // el plan repartía en «ventas por período», «rentabilidad» y «más
    // vendidos». Separarlas obligaba a elegir el mismo rango de fechas tres
    // veces para leer un mismo período.
    [
        'label' => 'Reportes',
        'icon' => 'ri-line-chart-line',
        'route' => 'reportes.index',
        'active' => 'reportes*',
        'permission' => 'reportes.ver',
    ],

    ['type' => 'title', 'label' => 'Sistema'],

    [
        'label' => 'Administración',
        'icon' => 'ri-settings-3-line',
        'active' => ['usuarios*', 'roles*'],
        // Sin 'permission' en el padre: cada hijo pide el suyo y MenuBuilder
        // oculta el grupo entero si no queda ninguno visible. Así alguien que
        // solo gestione roles no necesita también permiso sobre usuarios.
        'children' => [
            ['label' => 'Usuarios', 'route' => 'usuarios.index', 'active' => 'usuarios*', 'permission' => 'usuarios.ver'],
            ['label' => 'Roles y permisos', 'route' => 'roles.index', 'active' => 'roles*', 'permission' => 'roles.ver'],
        ],
    ],

];
