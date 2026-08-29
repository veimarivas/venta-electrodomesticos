<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Módulos del sistema y las acciones que admite cada uno.
     * El permiso resultante es "modulo.accion" (ej. ventas.crear).
     */
    private const MODULOS = [
        'personas' => ['ver', 'crear', 'editar', 'eliminar'],
        'trabajadores' => ['ver', 'crear', 'editar', 'eliminar'],
        'cargos' => ['ver', 'crear', 'editar', 'eliminar'],
        'categorias' => ['ver', 'crear', 'editar', 'eliminar'],
        'marcas' => ['ver', 'crear', 'editar', 'eliminar'],
        'productos' => ['ver', 'crear', 'editar', 'eliminar'],
        'unidades' => ['ver', 'crear', 'editar', 'eliminar'],
        'proveedores' => ['ver', 'crear', 'editar', 'eliminar'],
        'compras' => ['ver', 'crear', 'editar', 'eliminar', 'recepcionar'],
        'inventario' => ['ver', 'ajustar'],
        'stock' => ['ver'],
        // El arqueo lo abre y lo cierra quien está en el mostrador; verlo
        // -el histórico de cierres y sus diferencias- es de quien supervisa.
        'caja' => ['ver', 'gestionar'],
        'ventas' => ['ver', 'crear', 'anular'],
        // Los QR de cobro son dinero de la tienda: quien vende necesita verlos
        // para mostrarlos, pero registrarlos o cambiarles la fecha no.
        'qrs_cobro' => ['ver', 'crear', 'editar', 'eliminar'],
        'clientes' => ['ver', 'crear', 'editar', 'eliminar'],
        'reportes' => ['ver', 'ver_costos'],
        'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
        'roles' => ['ver', 'crear', 'editar', 'eliminar'],
    ];

    /**
     * Qué puede hacer cada rol. El rol 'admin' no se lista porque
     * AppServiceProvider le concede todo mediante Gate::before().
     */
    private const ROLES = [
        'supervisor' => [
            'personas.ver', 'personas.crear', 'personas.editar',
            'trabajadores.ver', 'trabajadores.crear', 'trabajadores.editar',
            'cargos.ver', 'cargos.crear', 'cargos.editar',
            'categorias.ver', 'categorias.crear', 'categorias.editar', 'categorias.eliminar',
            'marcas.ver', 'marcas.crear', 'marcas.editar', 'marcas.eliminar',
            'productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar',
            'unidades.ver', 'unidades.crear', 'unidades.editar', 'unidades.eliminar',
            'proveedores.ver', 'proveedores.crear', 'proveedores.editar',
            'compras.ver', 'compras.crear', 'compras.editar', 'compras.recepcionar',
            'inventario.ver', 'inventario.ajustar',
            'stock.ver',
            'caja.ver', 'caja.gestionar',
            'ventas.ver', 'ventas.crear', 'ventas.anular',
            'qrs_cobro.ver', 'qrs_cobro.crear', 'qrs_cobro.editar',
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'reportes.ver', 'reportes.ver_costos',
        ],
        'vendedor' => [
            'personas.ver',
            'categorias.ver',
            'marcas.ver',
            'productos.ver',
            'unidades.ver',
            'inventario.ver',
            'stock.ver',
            // Abre y cierra su turno; el histórico de cierres de todos es de
            // quien supervisa, no suyo.
            'caja.gestionar',
            'ventas.ver', 'ventas.crear',
            // Ver, no administrar: el vendedor muestra el QR en el mostrador.
            'qrs_cobro.ver',
            'clientes.ver', 'clientes.crear',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULOS as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permission::findOrCreate("{$modulo}.{$accion}", 'web');
            }
        }

        Role::findOrCreate('admin', 'web');

        foreach (self::ROLES as $rol => $permisos) {
            Role::findOrCreate($rol, 'web')->syncPermissions($permisos);
        }
    }
}
