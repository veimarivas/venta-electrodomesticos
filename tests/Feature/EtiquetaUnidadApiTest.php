<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Etiqueta imprimible de una unidad desde el teléfono.
 *
 * El QR se guarda como PNG dentro del PDF (DomPDF no dibuja bien el SVG); el
 * visor del teléfono lo abre y se imprime desde ahí.
 */
class EtiquetaUnidadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function unidad(): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'nombre' => 'Smart TV 55" 4K',
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'codigo_interno' => 'P001-2609-0001',
        ]);
    }

    public function test_devuelve_la_etiqueta_en_pdf(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson("/api/v1/unidades/{$unidad->id}/etiqueta");

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_acepta_un_tamano_de_etiqueta(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs($this->admin());

        foreach (['pequena', 'mediana', 'grande'] as $tamano) {
            $this->getJson("/api/v1/unidades/{$unidad->id}/etiqueta?tamano={$tamano}")
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_un_tamano_desconocido_cae_en_el_mediano(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs($this->admin());

        // No es un error: el controlador lo reemplaza por el mediano.
        $this->getJson("/api/v1/unidades/{$unidad->id}/etiqueta?tamano=inventado")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_exige_permiso_de_ver_unidades(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson("/api/v1/unidades/{$unidad->id}/etiqueta")->assertForbidden();
    }

    public function test_se_necesita_sesion(): void
    {
        $unidad = $this->unidad();

        $this->getJson("/api/v1/unidades/{$unidad->id}/etiqueta")->assertUnauthorized();
    }
}
