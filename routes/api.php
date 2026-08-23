<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\CompraController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DispositivoController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\PersonalController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\ProveedorController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — app Flutter del administrador
|--------------------------------------------------------------------------
| Versionada en la URL desde el primer día: cuando la app publicada en la
| tienda quede atrás, /v2 podrá cambiar sin romperle nada.
|
| Rutas y campos en español, en coherencia con las tablas.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ---- Público ----------------------------------------------------------
    // Límite más estrecho que el resto: es la puerta por la que se prueban
    // contraseñas, y aquí todavía no hay usuario al que atribuir el gasto.
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    // ---- Autenticado ------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/perfil', [AuthController::class, 'perfil'])->name('auth.perfil');

        // Teléfonos para el push.
        Route::get('/dispositivos', [DispositivoController::class, 'index'])->name('dispositivos.index');
        Route::post('/dispositivos', [DispositivoController::class, 'store'])->name('dispositivos.store');
        Route::delete('/dispositivos/{token}', [DispositivoController::class, 'destroy'])
            // El token viene en la URL y trae caracteres que el patrón por
            // defecto de Laravel cortaría.
            ->where('token', '.*')
            ->name('dispositivos.destroy');

        // Avisos.
        Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
        Route::post('/notificaciones/{id}/leida', [NotificacionController::class, 'marcarLeida'])
            ->name('notificaciones.leida');
        Route::post('/notificaciones/leidas', [NotificacionController::class, 'marcarTodasLeidas'])
            ->name('notificaciones.leidas');

        // ---- Lo que exige permiso -----------------------------------------
        // La app la puede tener un vendedor: los reportes se cierran con el
        // mismo permiso que en la web, no por confiar en el cliente.
        Route::middleware('permission:reportes.ver')->group(function () {
            Route::get('/dashboard/resumen', [DashboardController::class, 'resumen'])->name('dashboard.resumen');
            Route::get('/dashboard/grafica', [DashboardController::class, 'grafica'])->name('dashboard.grafica');
            Route::get('/dashboard/top-productos', [DashboardController::class, 'topProductos'])
                ->name('dashboard.top-productos');

            Route::get('/reportes/proveedores', [ReporteController::class, 'rentabilidadPorProveedor'])
                ->name('reportes.proveedores');
            Route::get('/reportes/compras/{compra}/rentabilidad', [ReporteController::class, 'rentabilidadDeCompra'])
                ->name('reportes.compra');
        });

        Route::middleware('permission:inventario.ver')->group(function () {
            Route::get('/inventario/stock-bajo', [ReporteController::class, 'stockBajo'])
                ->name('inventario.stock-bajo');
        });

        // Catálogo: solo consulta. Un vendedor con la app puede mirar precios
        // y existencias, que es para lo que la abre en el mostrador.
        Route::middleware('permission:productos.ver')->group(function () {
            Route::get('/catalogo/categorias', [CatalogoController::class, 'categorias'])
                ->name('catalogo.categorias');
            Route::get('/catalogo/marcas', [CatalogoController::class, 'marcas'])
                ->name('catalogo.marcas');
            Route::get('/catalogo/productos', [CatalogoController::class, 'productos'])
                ->name('catalogo.productos');
            Route::get('/catalogo/productos/{producto}', [CatalogoController::class, 'producto'])
                ->name('catalogo.producto');
        });

        // Personal y clientes: también solo consulta. Cada uno con su permiso,
        // porque quien lleva las ventas no tiene por qué ver la ficha laboral
        // de sus compañeros.
        Route::middleware('permission:cargos.ver')->group(function () {
            Route::get('/personal/cargos', [PersonalController::class, 'cargos'])
                ->name('personal.cargos');
        });

        Route::middleware('permission:trabajadores.ver')->group(function () {
            Route::get('/personal/trabajadores', [PersonalController::class, 'trabajadores'])
                ->name('personal.trabajadores');
            Route::get('/personal/trabajadores/{trabajador}', [PersonalController::class, 'trabajador'])
                ->name('personal.trabajador');
        });

        // Compras: consulta. Recepcionar genera las unidades físicas del
        // almacén, y eso se hace con la mercadería delante, no por API.
        Route::middleware('permission:proveedores.ver')->group(function () {
            Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
            Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])->name('proveedores.show');
        });

        Route::middleware('permission:compras.ver')->group(function () {
            Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
            Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
            Route::get('/compras/{compra}/unidades', [CompraController::class, 'unidades'])
                ->name('compras.unidades');
        });

        Route::middleware('permission:clientes.ver')->group(function () {
            Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
            Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        });

        // Alta rápida desde el mostrador, dentro de una venta.
        Route::post('/clientes', [ClienteController::class, 'store'])
            ->middleware('permission:clientes.crear')
            ->name('clientes.store');

        // ---- Punto de venta -----------------------------------------------
        // La única parte de la API que escribe. Existe porque en el mostrador
        // la cámara lee la etiqueta del aparato más rápido de lo que se teclea
        // un serial; la lógica sigue siendo la de RegistroDeVenta.
        Route::middleware('permission:ventas.crear')->group(function () {
            Route::get('/pos/buscar', [PosController::class, 'buscar'])->name('pos.buscar');
            Route::get('/pos/qrs', [PosController::class, 'qrs'])->name('pos.qrs');
            Route::post('/pos/cobrar', [PosController::class, 'cobrar'])->name('pos.cobrar');
        });

        Route::middleware('permission:ventas.ver')->group(function () {
            Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
            Route::get('/ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
        });
    });
});
