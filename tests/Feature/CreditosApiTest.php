<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La cartera por API.
 *
 * Escribe, como las entregas y el POS: cobrar una cuota pasa en el mostrador o
 * en la puerta del cliente, no delante del panel. Abrir un crédito sigue sin
 * poder hacerse desde el móvil.
 */
class CreditosApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function cajero(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function supervisor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    /**
     * Los importes viajan como número JSON, y json_decode devuelve `int` para
     * los que no tienen decimales. Comparar con `900.0` a secas falla por el
     * tipo, no por el valor.
     */
    private function importe(float $esperado): callable
    {
        return fn ($valor): bool => (float) $valor === $esperado;
    }

    /**
     * Atrasa un crédito ya creado.
     *
     * No se puede abrir uno con la primera cuota vencida —`PlanDeCuotas` lo
     * rechaza, y hace bien—, así que para probar la mora se mueven las fechas
     * después.
     */
    private function atrasar(Credito $credito, int $meses = 2): Credito
    {
        foreach ($credito->cuotas as $cuota) {
            $cuota->update(['vence_en' => $cuota->vence_en->subMonths($meses)]);
        }

        return $credito->refresh();
    }

    /** Crédito de 1200 en 4 cuotas de 300, sin inicial. */
    private function credito(int $cuotas = 4, string $primerVencimiento = '+1 month'): Credito
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
                    'numero_cuotas' => $cuotas,
                    'primer_vencimiento' => Carbon::parse($primerVencimiento)->format('Y-m-d'),
                ],
            ],
            userId: $this->supervisor()->id,
        );

        return $venta->credito;
    }

    public function test_lista_la_cartera_con_su_saldo_y_la_proxima_cuota(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->cajero());

        $this->getJson('/api/v1/creditos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $credito->id)
            ->assertJsonPath('data.0.saldo', $this->importe(1200))
            ->assertJsonPath('data.0.total_financiado', $this->importe(1200))
            ->assertJsonPath('data.0.proxima_cuota.numero', 1)
            ->assertJsonPath('data.0.proxima_cuota.falta', $this->importe(300))
            // La mora la decide el servidor: la fecha del teléfono puede estar
            // mal.
            ->assertJsonPath('data.0.esta_en_mora', false);
    }

    public function test_el_filtro_de_mora_deja_solo_a_quien_hay_que_llamar(): void
    {
        $alDia = $this->credito();
        $vencido = $this->atrasar($this->credito());

        Sanctum::actingAs($this->cajero());

        $respuesta = $this->getJson('/api/v1/creditos?filtro=mora');

        $respuesta->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($vencido->id, $respuesta->json('data.0.id'));
        $this->assertTrue($respuesta->json('data.0.esta_en_mora'));
        $this->assertNotSame($alDia->id, $respuesta->json('data.0.id'));
    }

    public function test_la_ficha_trae_el_plan_entero_y_los_pagos(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->cajero());

        $this->postJson("/api/v1/creditos/{$credito->id}/cobrar", [
            'monto' => 300,
            'metodo_pago' => 'efectivo',
        ])->assertOk();

        $this->getJson("/api/v1/creditos/{$credito->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data.cuotas')
            ->assertJsonPath('data.cuotas.0.esta_pagada', true)
            ->assertJsonPath('data.cuotas.1.esta_pagada', false)
            ->assertJsonCount(1, 'data.pagos')
            ->assertJsonPath('data.pagos.0.cuota_numero', 1)
            ->assertJsonPath('data.saldo', $this->importe(900));
    }

    public function test_cobrar_imputa_a_la_cuota_mas_antigua_y_devuelve_el_saldo(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->cajero());

        $this->postJson("/api/v1/creditos/{$credito->id}/cobrar", [
            'monto' => 300,
            'metodo_pago' => 'efectivo',
        ])
            ->assertOk()
            // Se devuelve el crédito entero: tras cobrar, lo que la pantalla
            // repinta es el saldo y el estado de las cuotas.
            ->assertJsonPath('data.saldo', $this->importe(900))
            ->assertJsonPath('data.proxima_cuota.numero', 2);
    }

    public function test_un_pago_que_cubre_cuota_y_media_toca_dos_con_un_recibo(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->cajero());

        $this->postJson("/api/v1/creditos/{$credito->id}/cobrar", [
            'monto' => 450,
            'metodo_pago' => 'efectivo',
        ])->assertOk();

        $ficha = $this->getJson("/api/v1/creditos/{$credito->id}")->assertOk();

        $pagos = $ficha->json('data.pagos');

        $this->assertCount(2, $pagos);
        // Una sola entrega de dinero: mismo recibo, dos imputaciones.
        $this->assertSame($pagos[0]['recibo'], $pagos[1]['recibo']);
    }

    public function test_cobrar_de_mas_contesta_422_con_su_mensaje(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->cajero());

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/cobrar", [
            'monto' => 5000,
            'metodo_pago' => 'efectivo',
        ]);

        // Un 500 aquí la app lo pintaría como «no hay conexión»; el cajero
        // tiene que leer que el pago supera el saldo.
        $respuesta->assertStatus(422)->assertJsonStructure(['message']);

        $this->assertStringContainsString('supera el saldo', $respuesta->json('message'));
    }

    public function test_el_pago_por_qr_exige_respaldo(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->cajero());

        $this->postJson("/api/v1/creditos/{$credito->id}/cobrar", [
            'monto' => 300,
            'metodo_pago' => 'qr',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_sin_permiso_de_cobrar_se_puede_mirar_pero_no_cobrar(): void
    {
        $credito = $this->credito();

        $miron = User::factory()->create(['is_active' => true]);
        $miron->givePermissionTo('creditos.ver');

        Sanctum::actingAs($miron);

        $this->getJson('/api/v1/creditos')->assertOk();
        $this->postJson("/api/v1/creditos/{$credito->id}/cobrar", [
            'monto' => 300,
            'metodo_pago' => 'efectivo',
        ])->assertForbidden();
    }

    public function test_sin_permiso_no_se_ve_la_cartera(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson('/api/v1/creditos')->assertForbidden();
    }

    public function test_sin_sesion_la_ruta_existe_pero_pide_credenciales(): void
    {
        $this->getJson('/api/v1/creditos')->assertUnauthorized();
    }
}
