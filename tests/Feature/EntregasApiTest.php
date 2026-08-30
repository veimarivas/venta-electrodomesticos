<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\ProgramacionDeEntregas;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Entregas por API: la mitad que faltaba.
 *
 * Es, junto al POS, la única parte de la API que **escribe**, y por la misma
 * razón: quien reparte lleva el móvil, no el panel. Programar sigue siendo del
 * mostrador.
 */
class EntregasApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function repartidor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    /** El serial es único por llamada: `unidades.serial` lleva índice único. */
    private function venta(?string $serial = null): Venta
    {
        $serial ??= 'SN-API-'.Unidad::max('id').'-'.uniqid();

        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => 1000,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 500,
            'precio_venta' => 1000,
            'serial' => $serial,
        ]);

        return app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1000, 'descuento' => 0]],
            cabecera: ['cliente_id' => Cliente::factory()->create()->id, 'metodo_pago' => 'efectivo'],
            userId: $this->repartidor()->id,
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function entrega(array $datos = [], ?string $serial = null): Entrega
    {
        $venta = $this->venta($serial);

        return app(ProgramacionDeEntregas::class)->programar(
            $venta,
            $venta->detalles->pluck('id')->all(),
            array_merge(['direccion' => 'Av. Siempre Viva 742'], $datos),
            $this->repartidor()->id,
        );
    }

    public function test_lista_las_entregas_con_su_direccion_y_sus_aparatos(): void
    {
        $this->entrega(
            ['referencia' => 'Portón verde', 'programada_para' => today()->toDateString()],
            serial: 'SN-API-1',
        );

        Sanctum::actingAs($this->repartidor());

        $respuesta = $this->getJson('/api/v1/entregas?filtro=abiertas');

        $respuesta->assertOk()
            ->assertJsonPath('data.0.direccion', 'Av. Siempre Viva 742')
            ->assertJsonPath('data.0.referencia', 'Portón verde')
            ->assertJsonPath('data.0.estado', 'pendiente')
            // El serial viaja en el mismo objeto: quien carga el camión no
            // puede estar pidiendo un endpoint por parada.
            ->assertJsonPath('data.0.aparatos.0.serial', 'SN-API-1');
    }

    public function test_el_filtro_mias_deja_solo_las_del_repartidor(): void
    {
        $mio = $this->repartidor();
        $ajena = $this->entrega();
        $propia = $this->entrega();

        app(ProgramacionDeEntregas::class)->despachar($propia, $mio->id, $mio->id);

        Sanctum::actingAs($mio);

        $respuesta = $this->getJson('/api/v1/entregas?mias=1');

        $respuesta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propia->id);

        $this->assertNotSame($ajena->id, $respuesta->json('data.0.id'));
    }

    public function test_se_despacha_desde_el_telefono_y_queda_a_nombre_de_quien_llama(): void
    {
        $entrega = $this->entrega();
        $repartidor = $this->repartidor();

        Sanctum::actingAs($repartidor);

        // Sin repartidor_id en el cuerpo: desde el móvil, el que despacha es
        // casi siempre el que se lo lleva.
        $this->postJson("/api/v1/entregas/{$entrega->id}/despachar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_ruta')
            ->assertJsonPath('data.repartidor_id', $repartidor->id);
    }

    public function test_se_confirma_la_entrega_desde_el_camion(): void
    {
        $entrega = $this->entrega(['con_instalacion' => true]);
        $repartidor = $this->repartidor();

        Sanctum::actingAs($repartidor);

        $this->postJson("/api/v1/entregas/{$entrega->id}/despachar")->assertOk();

        $this->postJson("/api/v1/entregas/{$entrega->id}/confirmar", [
            'recibida_por' => 'Doña Rosa',
            'instalada' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'entregada')
            ->assertJsonPath('data.recibida_por', 'Doña Rosa');

        $this->assertNotNull($entrega->refresh()->instalada_en);
    }

    public function test_confirmar_sin_decir_quien_recibio_no_pasa_de_la_validacion(): void
    {
        $entrega = $this->entrega();

        Sanctum::actingAs($this->repartidor());

        $this->postJson("/api/v1/entregas/{$entrega->id}/confirmar", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('recibida_por');
    }

    public function test_se_marca_el_fallo_y_se_reprograma(): void
    {
        $entrega = $this->entrega();

        Sanctum::actingAs($this->repartidor());

        $this->postJson("/api/v1/entregas/{$entrega->id}/fallar", ['motivo' => 'No había nadie'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'fallida')
            ->assertJsonPath('data.motivo_fallo', 'No había nadie');

        $this->postJson("/api/v1/entregas/{$entrega->id}/reprogramar", [
            'programada_para' => today()->addDays(2)->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.programada_para', today()->addDays(2)->toDateString());
    }

    public function test_un_error_de_negocio_contesta_422_y_no_500(): void
    {
        $entrega = $this->entrega();

        Sanctum::actingAs($this->repartidor());

        $this->postJson("/api/v1/entregas/{$entrega->id}/confirmar", ['recibida_por' => 'El cliente'])
            ->assertOk();

        // Un 500 aquí haría que la app dijera «no hay conexión» cuando el
        // problema es que la entrega ya se hizo.
        $this->postJson("/api/v1/entregas/{$entrega->id}/confirmar", ['recibida_por' => 'Otra vez'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_sin_permiso_no_se_listan_ni_se_mueven(): void
    {
        $entrega = $this->entrega();

        Sanctum::actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson('/api/v1/entregas')->assertForbidden();
        $this->postJson("/api/v1/entregas/{$entrega->id}/despachar")->assertForbidden();
    }

    public function test_sin_sesion_la_ruta_existe_pero_pide_credenciales(): void
    {
        // 401 y no 404: es la diferencia que distingue «no subí el código» de
        // «no tengo permiso» al comprobar un despliegue.
        $this->getJson('/api/v1/entregas')->assertUnauthorized();
    }
}
