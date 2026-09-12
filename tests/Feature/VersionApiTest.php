<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de versión entre la app y la API.
 *
 * La app lo consulta al arrancar para saber si quedó atrás. No exige sesión:
 * un APK viejo tiene que poder enterarse aunque su token ya no sirva.
 */
class VersionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_devuelve_la_version_sin_sesion(): void
    {
        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.api', 1)
            ->assertJsonPath('data.app_minima', config('ventas.app_minima'));
    }

    public function test_la_version_minima_sale_de_la_configuracion(): void
    {
        config(['ventas.app_minima' => '2.5.0']);

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.app_minima', '2.5.0');
    }
}
