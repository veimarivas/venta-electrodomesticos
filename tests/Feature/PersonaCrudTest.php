<?php

namespace Tests\Feature;

use App\Livewire\Personas\Index;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonaCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->syncRoles('admin');
    }

    /**
     * @return array<string, string>
     */
    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'carnet' => '8123456',
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Rivas',
            'apellido_materno' => 'Quispe',
            'celular' => '71234567',
            'direccion' => 'Av. Siempre Viva 742',
            'correo' => 'juan@correo.com',
            'fecha_nacimiento' => '1990-05-14',
        ], $sobrescribir);
    }

    public function test_el_listado_muestra_diez_registros_por_pagina(): void
    {
        // 23 explícitas + la ficha autogenerada del usuario admin = 24.
        Persona::factory()->count(23)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('personas', fn ($personas) => $personas->count() === 10
                && $personas->total() === 24
                && $personas->lastPage() === 3);
    }

    public function test_el_buscador_filtra_por_carnet_y_reinicia_la_paginacion(): void
    {
        Persona::factory()->count(15)->create();
        Persona::factory()->create(['carnet' => '9988776', 'nombres' => 'Buscada']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('paginators.page', 2)
            ->set('buscar', '9988776')
            ->assertViewHas('personas', fn ($personas) => $personas->total() === 1
                && $personas->first()->nombres === 'Buscada');
    }

    public function test_el_formulario_no_es_valido_mientras_falten_campos_obligatorios(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('carnet', '8123456')
            ->assertSet('formularioValido', false)
            ->set('nombres', 'Juan')
            ->assertSet('formularioValido', false)
            ->set('apellido_paterno', 'Rivas')
            // Con los tres obligatorios completos, ya se puede registrar.
            ->assertSet('formularioValido', true);
    }

    public function test_valida_cada_campo_apenas_cambia(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('carnet', '12')
            ->assertHasErrors(['carnet' => 'regex'])
            ->set('correo', 'no-es-un-correo')
            ->assertHasErrors(['correo' => 'email'])
            ->set('fecha_nacimiento', now()->addDay()->format('Y-m-d'))
            ->assertHasErrors(['fecha_nacimiento' => 'before']);
    }

    public function test_el_carnet_solo_acepta_entre_7_y_11_numeros(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('carnet', '12345')
            ->assertHasErrors(['carnet' => 'regex'])
            ->set('carnet', '123456')
            ->assertHasErrors(['carnet' => 'regex'])
            ->set('carnet', '123456789012')
            ->assertHasErrors(['carnet' => 'regex'])
            ->set('carnet', '8123456')
            ->assertHasNoErrors(['carnet'])
            ->set('carnet', '12345678901')
            ->assertHasNoErrors(['carnet'])
            ->set('carnet', 'abcdefg')
            ->assertHasErrors(['carnet' => 'regex']);
    }

    public function test_el_nombre_y_los_apellidos_solo_aceptan_letras(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombres', 'Juan 123')
            ->assertHasErrors(['nombres' => 'regex'])
            ->set('nombres', 'Juan Carlos')
            ->assertHasNoErrors(['nombres'])
            ->set('apellido_paterno', 'Rivas2')
            ->assertHasErrors(['apellido_paterno' => 'regex'])
            ->set('apellido_materno', 'Quispe_')
            ->assertHasErrors(['apellido_materno' => 'regex']);
    }

    public function test_requiere_al_menos_un_apellido(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('carnet', '8123456')
            ->set('nombres', 'Juan')
            ->assertSet('formularioValido', false)
            ->set('apellido_materno', 'Quispe')
            ->assertSet('formularioValido', true)
            ->set('apellido_materno', '')
            ->set('apellido_paterno', 'Rivas')
            ->assertSet('formularioValido', true);
    }

    public function test_el_celular_debe_tener_exactamente_8_numeros(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('celular', '123')
            ->assertHasErrors(['celular' => 'regex'])
            ->set('celular', '123456789')
            ->assertHasErrors(['celular' => 'regex'])
            ->set('celular', '71234567')
            ->assertHasNoErrors(['celular']);
    }

    public function test_los_mensajes_de_validacion_estan_en_espanol(): void
    {
        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombres', 'A');

        $this->assertStringContainsString(
            'al menos 2 caracteres',
            $componente->errors()->first('nombres')
        );
    }

    public function test_registra_una_persona_y_notifica(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-persona')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Persona registrada correctamente.');

        $this->assertDatabaseHas('personas', [
            'carnet' => '8123456',
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Rivas',
        ]);
    }

    public function test_no_permite_dos_personas_con_el_mismo_carnet(): void
    {
        Persona::factory()->create(['carnet' => '8123456']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasErrors(['carnet' => 'unique']);

        $this->assertSame(1, Persona::where('carnet', '8123456')->count());
    }

    public function test_al_editar_el_carnet_propio_no_choca_consigo_mismo(): void
    {
        $persona = Persona::factory()->create(['carnet' => '8123456']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $persona->id)
            ->assertSet('carnet', '8123456')
            ->set('nombres', 'Nombre Cambiado')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Persona actualizada correctamente.');

        $this->assertSame('Nombre Cambiado', $persona->fresh()->nombres);
    }

    public function test_los_campos_opcionales_vacios_se_guardan_como_null(): void
    {
        // Si se guardaran como cadena vacía, dos personas sin correo
        // chocarían contra el índice único.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['correo' => '', 'celular' => '', 'apellido_materno' => '']))
            ->call('guardar')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['carnet' => '7000001', 'correo' => '', 'celular' => '', 'apellido_materno' => '']))
            ->call('guardar')
            ->assertHasNoErrors();

        // 2 creadas en el formulario + las fichas autogeneradas de los 2
        // usuarios admin usados arriba (sin correo) = 4.
        $this->assertSame(4, Persona::whereNull('correo')->count());
    }

    public function test_elimina_una_persona(): void
    {
        $persona = Persona::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $persona->id)
            ->assertSet('eliminarId', $persona->id)
            ->call('eliminar')
            ->assertDispatched('cerrar-modal-eliminar')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Persona eliminada correctamente.');

        $this->assertSoftDeleted('personas', ['id' => $persona->id]);
    }

    public function test_no_se_puede_eliminar_una_persona_con_cuenta(): void
    {
        $persona = Persona::factory()->create();
        User::factory()->create(['persona_id' => $persona->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $persona->id)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('personas', ['id' => $persona->id]);
    }

    public function test_un_vendedor_puede_ver_pero_no_modificar(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');
        $persona = Persona::factory()->create();

        $this->actingAs($vendedor)->get('/personas')->assertOk();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('confirmarEliminar', $persona->id)
            ->assertForbidden();
    }

    public function test_un_usuario_sin_permiso_no_entra_al_listado(): void
    {
        $sinRol = User::factory()->create();

        $this->actingAs($sinRol)->get('/personas')->assertForbidden();
    }
}
