<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Notifications\CuotaPorCobrarPush;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * El aviso diario de cuotas.
 *
 * No hay columna de «ya avisado»: el disparo son dos fechas exactas —el día
 * del vencimiento y el siguiente—, así que repetir el comando el mismo día
 * vuelve a avisar de lo mismo, pero al día siguiente ya no. Es la guarda más
 * barata de mantener, porque no hay estado que se pueda quedar desfasado.
 */
class AvisoDeCuotasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function creditoQueVence(string $primerVencimiento): Credito
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => 1200,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 600,
            'precio_venta' => 1200,
        ]);

        $venta = app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1200, 'descuento' => 0]],
            cabecera: [
                'cliente_id' => Cliente::factory()->create()->id,
                'metodo_pago' => 'credito',
                'credito' => [
                    'cuota_inicial' => 0,
                    'numero_cuotas' => 4,
                    'primer_vencimiento' => $primerVencimiento,
                ],
            ],
            userId: User::factory()->create(['is_active' => true])->syncRoles('supervisor')->id,
        );

        return $venta->credito;
    }

    public function test_avisa_de_la_cuota_que_vence_hoy(): void
    {
        Notification::fake();

        $this->creditoQueVence(today()->format('Y-m-d'));

        $this->artisan('cuotas:avisar')->assertSuccessful();

        Notification::assertSentTimes(CuotaPorCobrarPush::class, 1);
    }

    public function test_no_avisa_de_una_cuota_que_todavia_falta(): void
    {
        Notification::fake();

        $this->creditoQueVence(today()->addMonth()->format('Y-m-d'));

        $this->artisan('cuotas:avisar')->assertSuccessful();

        // Avisar con un mes de antelación es ruido: para cuando llegue la
        // fecha, nadie recordará el aviso.
        Notification::assertNothingSent();
    }

    public function test_no_avisa_de_una_cuota_ya_pagada(): void
    {
        Notification::fake();

        $credito = $this->creditoQueVence(today()->format('Y-m-d'));
        $credito->cuotas->first()->update([
            'monto_pagado' => $credito->cuotas->first()->monto,
            'pagada_en' => now(),
        ]);

        $this->artisan('cuotas:avisar')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_no_avisa_de_las_cuotas_de_un_credito_anulado(): void
    {
        Notification::fake();

        $credito = $this->creditoQueVence(today()->format('Y-m-d'));
        $credito->update(['estado' => 'anulado']);

        // Mandaría a alguien a reclamar una deuda que ya no existe.
        $this->artisan('cuotas:avisar')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_el_aviso_va_a_quien_puede_ver_la_cartera(): void
    {
        Notification::fake();

        $supervisor = User::factory()->create(['is_active' => true])->syncRoles('supervisor');
        $sinPermiso = User::factory()->create(['is_active' => true]);

        $this->creditoQueVence(today()->format('Y-m-d'));

        $this->artisan('cuotas:avisar')->assertSuccessful();

        Notification::assertSentTo($supervisor, CuotaPorCobrarPush::class);
        Notification::assertNotSentTo($sinPermiso, CuotaPorCobrarPush::class);
    }
}
