<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\User;
use App\Models\Unidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LocalDataSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->crearPermisos();
        $this->crearRoles();
        $this->crearCategorias();
        $this->crearMarcas();
        $this->crearAdmin();
        $this->crearProducto();
        $this->crearUnidad();
    }

    private function crearPermisos(): void
    {
        $modulos = [
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
            'caja' => ['ver', 'gestionar'],
            'ventas' => ['ver', 'crear', 'anular'],
            'creditos' => ['ver', 'crear', 'cobrar'],
            'entregas' => ['ver', 'crear', 'gestionar'],
            'reparaciones' => ['ver', 'recibir', 'atender'],
            'qrs_cobro' => ['ver', 'crear', 'editar', 'eliminar'],
            'clientes' => ['ver', 'crear', 'editar', 'eliminar'],
            'reportes' => ['ver', 'ver_costos'],
            'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
            'roles' => ['ver', 'crear', 'editar', 'eliminar'],
        ];

        foreach ($modulos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permission::findOrCreate("{$modulo}.{$accion}", 'web');
            }
        }
    }

    private function crearRoles(): void
    {
        $admin = Role::findOrCreate('admin', 'web');

        $supervisor = Role::findOrCreate('supervisor', 'web');
        $supervisor->syncPermissions([
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
            'creditos.ver', 'creditos.crear', 'creditos.cobrar',
            'entregas.ver', 'entregas.crear', 'entregas.gestionar',
            'reparaciones.ver', 'reparaciones.recibir', 'reparaciones.atender',
            'qrs_cobro.ver', 'qrs_cobro.crear', 'qrs_cobro.editar',
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'reportes.ver', 'reportes.ver_costos',
            'caja.ver', 'caja.gestionar',
        ]);

        $vendedor = Role::findOrCreate('vendedor', 'web');
        $vendedor->syncPermissions([
            'personas.ver',
            'categorias.ver',
            'marcas.ver',
            'productos.ver',
            'unidades.ver',
            'inventario.ver',
            'stock.ver',
            'caja.gestionar',
            'ventas.ver', 'ventas.crear',
            'creditos.ver', 'creditos.cobrar',
            'entregas.ver', 'entregas.crear', 'entregas.gestionar',
            'reparaciones.ver', 'reparaciones.recibir',
            'qrs_cobro.ver',
            'clientes.ver', 'clientes.crear',
        ]);
    }

    private function crearCategorias(): void
    {
        // Raíces
        $lineaBlanca = Categoria::updateOrCreate(
            ['slug' => 'linea-blanca'],
            ['nombre' => 'Linea Blanca', 'descripcion' => 'Electrodomésticos grandes para el hogar', 'posicion' => 0, 'activo' => true]
        );

        $lineaNegra = Categoria::updateOrCreate(
            ['slug' => 'linea-negra'],
            ['nombre' => 'Linea Negra', 'descripcion' => 'Esta gama agrupa todos los dispositivos electrónicos cuyo propósito principal es el ocio', 'posicion' => 0, 'activo' => true]
        );

        // Hijos de Línea Blanca
        Categoria::updateOrCreate(
            ['slug' => 'refrigeracion'],
            ['padre_id' => $lineaBlanca->id, 'nombre' => 'Refrigeración', 'descripcion' => 'Refrigeradores y congeladores', 'posicion' => 0, 'activo' => true]
        );

        Categoria::updateOrCreate(
            ['slug' => 'lavado'],
            ['padre_id' => $lineaBlanca->id, 'nombre' => 'Lavado', 'descripcion' => 'Lavadoras, secadoras y centros de lavado.', 'posicion' => 0, 'activo' => true]
        );

        Categoria::updateOrCreate(
            ['slug' => 'climatizacion'],
            ['padre_id' => $lineaBlanca->id, 'nombre' => 'Climatización', 'descripcion' => '', 'posicion' => 0, 'activo' => true]
        );

        // Hijos de Línea Negra
        Categoria::updateOrCreate(
            ['slug' => 'televisores'],
            ['padre_id' => $lineaNegra->id, 'nombre' => 'Televisores', 'descripcion' => '', 'posicion' => 0, 'activo' => true]
        );

        Categoria::updateOrCreate(
            ['slug' => 'sonido'],
            ['padre_id' => $lineaNegra->id, 'nombre' => 'Sonido', 'descripcion' => '', 'posicion' => 0, 'activo' => true]
        );

        Categoria::updateOrCreate(
            ['slug' => 'cine-en-casa'],
            ['padre_id' => $lineaNegra->id, 'nombre' => 'Cine en casa', 'descripcion' => '', 'posicion' => 0, 'activo' => true]
        );
    }

    private function crearMarcas(): void
    {
        $marcas = [
            ['nombre' => 'Samsung', 'slug' => 'samsung', 'logo_ruta' => 'marcas/C2ez1wTp0EmJvZ6YyXc1QHXJtKQD5aKJJ3z08oAL.png'],
            ['nombre' => 'Huawei', 'slug' => 'huawei', 'logo_ruta' => 'marcas/8CGlNjt28oBbp7KQ6zoGMrUi7ilwGVsRmn8XA4Qn.webp'],
            ['nombre' => 'Sony', 'slug' => 'sony', 'logo_ruta' => 'marcas/dZOo13HEqzbfXsN7NJeKHljNmaOBiEUKDWjCcRWL.png'],
            ['nombre' => 'LG', 'slug' => 'lg', 'logo_ruta' => 'marcas/nhmaKnaBCfrRMwHIN17yU5v5ILDvBffNq8blcTDe.webp'],
            ['nombre' => 'Bosch', 'slug' => 'bosch', 'logo_ruta' => 'marcas/2bquAFY2hUvQPn4dpxCWH7MmqR4CdS8XOZHfqLnJ.png'],
            ['nombre' => 'TCL', 'slug' => 'tcl', 'logo_ruta' => 'marcas/BQTpIy7Ob7ZQtMOVDPdSIoB0KpCSicynRu9hOSm4.png'],
        ];

        foreach ($marcas as $marca) {
            Marca::updateOrCreate(
                ['slug' => $marca['slug']],
                ['nombre' => $marca['nombre'], 'logo_ruta' => $marca['logo_ruta'], 'activa' => true]
            );
        }
    }

    private function crearAdmin(): void
    {
        // Crear persona para el admin
        $persona = Persona::updateOrCreate(
            ['carnet' => '00001'],
            [
                'nombres' => 'Administrador',
                'apellido_paterno' => 'Sistema',
                'correo' => 'admin@gmail.com',
            ]
        );

        // Crear usuario admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrador',
                'persona_id' => $persona->id,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles('admin');
    }

    private function crearProducto(): void
    {
        $categoriaTv = Categoria::where('slug', 'televisores')->first();
        $marcaTcl = Marca::where('slug', 'tcl')->first();

        if ($categoriaTv && $marcaTcl) {
            Producto::updateOrCreate(
                ['slug' => 'tcl-85-serie-qm51l-qd-mini-led-qled-4k-uhd-hdr-smart-google-tv'],
                [
                    'categoria_id' => $categoriaTv->id,
                    'marca_id' => $marcaTcl->id,
                    'nombre' => 'TCL 85" Serie QM51L QD-Mini LED QLED 4K UHD HDR Smart Google TV',
                    'modelo' => 'QM51L',
                    'descripcion' => "TCL QM51L Series Smart TV, The New Definition of Affordable Premium, es ideal para películas de acción rápida, deportes y juegos de siguiente nivel.",
                    'imagen' => 'productos/wSvcWGy3vU2y7r3xi2CtC4SYd6ps1TCW168J7NbL.png',
                    'precio_venta' => 8900.00,
                    'descuento_maximo' => 8500.00,
                    'stock_minimo' => 10,
                    'meses_garantia' => 12,
                    'activo' => true,
                    'tiene_serial' => true,
                ]
            );

            $producto = Producto::where('slug', 'tcl-85-serie-qm51l-qd-mini-led-qled-4k-uhd-hdr-smart-google-tv')->first();

            if ($producto && $producto->especificaciones()->count() === 0) {
                // Características como filas, en el orden en que se registraron.
                foreach ([
                    'Sound technology' => 'Dolby Atmos',
                    'Display' => 'Mini-LED',
                    'Resolution' => '3840 x 2160',
                    'Screen size' => '84.5 in',
                    'Platform' => 'Google TV',
                    'Refresh rate' => '60 Hz',
                ] as $clave => $valor) {
                    $producto->especificaciones()->create([
                        'clave' => $clave,
                        'valor' => $valor,
                        'posicion' => $producto->especificaciones()->count(),
                    ]);
                }
            }
        }
    }

    private function crearUnidad(): void
    {
        $producto = Producto::where('slug', 'tcl-85-serie-qm51l-qd-mini-led-qled-4k-uhd-hdr-smart-google-tv')->first();

        if ($producto) {
            Unidad::updateOrCreate(
                ['codigo_interno' => 'P001-2609-0001'],
                [
                    'producto_id' => $producto->id,
                    'serial' => null,
                    'costo_unitario' => 9500.00,
                    'precio_venta' => 8900.00,
                    'estado' => 'en_stock',
                    'ingresado_en' => now()->subDay(),
                ]
            );
        }
    }
}
