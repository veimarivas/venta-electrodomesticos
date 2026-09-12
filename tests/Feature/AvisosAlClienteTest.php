<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Reparacion;
use App\Models\Unidad;
use App\Models\User;
use App\Support\ProgramacionDeEntregas;
use App\Support\RegistroDeVenta;
use App\Support\ServicioTecnico;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Avisos al cliente: la entrega que sale y la reparación que está lista.
 *
 * El transporte se elige con `config/avisos.php`; aquí se fija el canal `log`
 * para poder leer el aviso sin depender de Firebase ni de un proveedor de
 * WhatsApp.
 */
class AvisosAlClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        config(['avisos.canal' => 'log']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function cliente(string $celular = '70000000'): Cliente
    {
        return Cliente::factory()->for(
            Persona::factory()->state(['celular' => $celular, 'correo' => 'cli@ejemplo.com'])
        )->create();
    }

    private function unidadVendida(Cliente $cliente): Unidad
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => 1000,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
                'meses_garantia' => 12,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 500,
            'precio_venta' => 1000,
        ]);

        app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1000, 'descuento' => 0]],
            cabecera: ['cliente_id' => $cliente->id, 'metodo_pago' => 'efectivo'],
            userId: $this->admin()->id,
        );

        return $unidad->refresh();
    }

    public function test_al_despachar_la_entrega_se_avisa_al_cliente(): void
    {
        $cliente = $this->cliente('76543210');
        $unidad = $this->unidadVendida($cliente);
        $venta = $unidad->ventaDetalle->venta;

        $entrega = app(ProgramacionDeEntregas::class)->programar(
            $venta,
            [$unidad->ventaDetalle->id],
            ['direccion' => 'Av. Siempre Viva 742'],
            $this->admin()->id,
        );

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(
            fn (string $mensaje): bool => str_contains($mensaje, '76543210')
                && str_contains($mensaje, 'salió en camino')
        );

        app(ProgramacionDeEntregas::class)->despachar(
            $entrega,
            $this->admin()->id,
            $this->admin()->id,
        );
    }

    public function test_al_marcar_lista_la_reparacion_se_avisa_al_cliente(): void
    {
        $cliente = $this->cliente('71112222');
        $unidad = $this->unidadVendida($cliente);

        $reparacion = app(ServicioTecnico::class)->recibir(
            $unidad,
            ['falla_reportada' => 'No enciende', 'cliente_id' => $cliente->id],
            $this->admin()->id,
        );

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(
            fn (string $mensaje): bool => str_contains($mensaje, '71112222')
                && str_contains($mensaje, 'listo para recoger')
        );

        app(ServicioTecnico::class)->marcarLista($reparacion, 'Se cambió la fuente');
    }

    public function test_el_canal_correo_sin_direccion_no_falla(): void
    {
        config(['avisos.canal' => 'correo']);

        // Sin correo el aviso no se puede entregar, pero la operación no puede
        // caerse por eso.
        Log::shouldReceive('warning')->once();

        $entregado = app(\App\Support\AvisosAlCliente::class)->enviar(
            '70000000',
            null,
            'Mensaje de prueba',
        );

        $this->assertFalse($entregado);
    }
}
