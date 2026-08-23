<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReciboController;
use App\Http\Controllers\SearchController;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Las rutas de autenticación (login, logout, recuperación de contraseña y
| 2FA) las registra Laravel Fortify automáticamente; aquí solo va lo propio
| de la aplicación.
|
*/

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::view('/stock', 'backend.stock.index', [
        'title' => 'Stock Actual',
        'breadcrumbs' => ['Inicio' => null, 'Stock Actual' => null],
    ])->middleware('permission:stock.ver')->name('stock.index');

    Route::get('/buscar', SearchController::class)->name('search');

    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');

    // Cada CRUD vive dentro de su componente Livewire, por eso una sola ruta.
    Route::view('/personas', 'backend.personas.index', [
        'title' => 'Personas',
        'breadcrumbs' => ['Inicio' => null, 'Personas' => null],
    ])->middleware('permission:personas.ver')->name('personas.index');

    Route::view('/trabajadores', 'backend.trabajadores.index', [
        'title' => 'Trabajadores',
        'breadcrumbs' => ['Inicio' => null, 'Personal' => null, 'Trabajadores' => null],
    ])->middleware('permission:trabajadores.ver')->name('trabajadores.index');

    Route::view('/reportes', 'backend.reportes.index', [
        'title' => 'Reportes',
        'breadcrumbs' => ['Inicio' => null, 'Análisis' => null, 'Reportes' => null],
    ])->middleware('permission:reportes.ver')->name('reportes.index');

    Route::view('/ventas/nueva', 'backend.ventas.pos', [
        'title' => 'Punto de venta',
        'breadcrumbs' => ['Inicio' => null, 'Ventas' => null, 'Punto de venta' => null],
    ])->middleware('permission:ventas.crear')->name('ventas.create');

    Route::view('/ventas', 'backend.ventas.index', [
        'title' => 'Historial de ventas',
        'breadcrumbs' => ['Inicio' => null, 'Ventas' => null, 'Historial' => null],
    ])->middleware('permission:ventas.ver')->name('ventas.index');

    Route::get('/ventas/{venta}', function (\App\Models\Venta $venta) {
        return view('backend.ventas.detalle', [
            'title' => 'Venta '.$venta->codigo,
            'breadcrumbs' => ['Inicio' => null, 'Ventas' => null, $venta->codigo => route('ventas.index')],
            'venta' => $venta,
        ]);
    })->middleware('permission:ventas.ver')->name('ventas.show');

    // Recibo en PDF. Se genera al vuelo desde la venta guardada: no se
    // archiva un PDF por venta, porque una venta no se edita nunca.
    Route::get('/ventas/{venta}/recibo', ReciboController::class)
        ->middleware('permission:ventas.ver')->name('ventas.recibo');

    Route::view('/ventas/qr-cobro', 'backend.qrs-cobro.index', [
        'title' => 'QR de cobro',
        'breadcrumbs' => ['Inicio' => null, 'Ventas' => null, 'QR de cobro' => null],
    ])->middleware('permission:qrs_cobro.ver')->name('ventas.qrs-cobro.index');

    Route::view('/clientes', 'backend.clientes.index', [
        'title' => 'Clientes',
        'breadcrumbs' => ['Inicio' => null, 'Ventas' => null, 'Clientes' => null],
    ])->middleware('permission:clientes.ver')->name('clientes.index');

    Route::view('/categorias', 'backend.categorias.index', [
        'title' => 'Categorías',
        'breadcrumbs' => ['Inicio' => null, 'Catálogo' => null, 'Categorías' => null],
    ])->middleware('permission:categorias.ver')->name('categorias.index');

    Route::view('/marcas', 'backend.marcas.index', [
        'title' => 'Marcas',
        'breadcrumbs' => ['Inicio' => null, 'Catálogo' => null, 'Marcas' => null],
    ])->middleware('permission:marcas.ver')->name('marcas.index');

    Route::view('/productos', 'backend.productos.index', [
        'title' => 'Productos',
        'breadcrumbs' => ['Inicio' => null, 'Catálogo' => null, 'Productos' => null],
    ])->middleware('permission:productos.ver')->name('productos.index');

    Route::view('/cargos', 'backend.cargos.index', [
        'title' => 'Cargos',
        'breadcrumbs' => ['Inicio' => null, 'Personal' => null, 'Cargos' => null],
    ])->middleware('permission:cargos.ver')->name('cargos.index');

    Route::view('/proveedores', 'backend.proveedores.index', [
        'title' => 'Proveedores',
        'breadcrumbs' => ['Inicio' => null, 'Compras' => null, 'Proveedores' => null],
    ])->middleware('permission:proveedores.ver')->name('proveedores.index');

    Route::get('/proveedores/{proveedor}', function (Proveedor $proveedor) {
        return view('backend.proveedores.show', [
            'title' => $proveedor->nombre,
            'breadcrumbs' => [
                'Inicio' => null,
                'Compras' => null,
                'Proveedores' => route('proveedores.index'),
                $proveedor->nombre => null,
            ],
            'proveedor' => $proveedor,
        ]);
    })->middleware('permission:proveedores.ver')->name('proveedores.show');

    Route::view('/compras', 'backend.compras.index', [
        'title' => 'Órdenes de compra',
        'breadcrumbs' => ['Inicio' => null, 'Compras' => null, 'Órdenes de compra' => null],
    ])->middleware('permission:compras.ver')->name('compras.index');

    Route::get('/compras/{compra}', function (\App\Models\Compra $compra) {
        return view('backend.compras.show', [
            'title' => 'Compra '.$compra->codigo,
            'breadcrumbs' => ['Inicio' => null, 'Compras' => null, $compra->codigo => route('compras.index')],
            'compra' => $compra,
        ]);
    })->middleware('permission:compras.ver')->name('compras.show');

    // Hojas de etiquetas: se abren en pestaña aparte, con layout propio.
    Route::get('/etiquetas/compra/{compra}', [EtiquetaController::class, 'compra'])
        ->middleware('permission:unidades.ver')->name('etiquetas.compra');

    Route::get('/etiquetas/unidades', [EtiquetaController::class, 'unidades'])
        ->middleware('permission:unidades.ver')->name('etiquetas.unidades');

    Route::view('/inventario/unidades', 'backend.unidades.index', [
        'title' => 'Unidades',
        'breadcrumbs' => ['Inicio' => null, 'Inventario' => null, 'Unidades' => null],
    ])->middleware('permission:unidades.ver')->name('inventario.unidades.index');

    Route::view('/inventario/kardex', 'backend.inventario.kardex', [
        'title' => 'Kardex',
        'breadcrumbs' => ['Inicio' => null, 'Inventario' => null, 'Kardex' => null],
    ])->middleware('permission:inventario.ver')->name('inventario.kardex');

    Route::view('/usuarios', 'backend.usuarios.index', [
        'title' => 'Usuarios',
        'breadcrumbs' => ['Inicio' => null, 'Sistema' => null, 'Usuarios' => null],
    ])->middleware('permission:usuarios.ver')->name('usuarios.index');

    Route::view('/roles', 'backend.roles.index', [
        'title' => 'Roles y permisos',
        'breadcrumbs' => ['Inicio' => null, 'Sistema' => null, 'Roles y permisos' => null],
    ])->middleware('permission:roles.ver')->name('roles.index');
});
