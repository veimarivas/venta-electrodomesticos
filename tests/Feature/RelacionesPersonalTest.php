<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelacionesPersonalTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_pertenece_a_una_persona(): void
    {
        $persona = Persona::factory()->create();
        $user = User::factory()->create(['persona_id' => $persona->id]);

        $this->assertTrue($user->persona->is($persona));
        $this->assertTrue($persona->fresh()->user->is($user));
    }

    public function test_una_persona_no_puede_estar_en_dos_cuentas(): void
    {
        $persona = Persona::factory()->create();
        User::factory()->create(['persona_id' => $persona->id]);

        $this->expectException(QueryException::class);

        User::factory()->create(['persona_id' => $persona->id]);
    }

    public function test_un_trabajador_pertenece_a_una_persona_y_a_un_cargo(): void
    {
        $persona = Persona::factory()->create();
        $cargo = Cargo::factory()->create(['nombre' => 'Vendedor']);

        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $cargo->id,
        ]);

        $this->assertTrue($trabajador->persona->is($persona));
        $this->assertTrue($trabajador->cargo->is($cargo));
        $this->assertTrue($persona->fresh()->trabajador->is($trabajador));
    }

    public function test_una_persona_no_puede_ser_dos_trabajadores(): void
    {
        $persona = Persona::factory()->create();
        Trabajador::factory()->create(['persona_id' => $persona->id]);

        $this->expectException(QueryException::class);

        Trabajador::factory()->create(['persona_id' => $persona->id]);
    }

    public function test_un_cargo_agrupa_muchos_trabajadores(): void
    {
        $cargo = Cargo::factory()->create();
        Trabajador::factory()->count(3)->create(['cargo_id' => $cargo->id]);

        $this->assertCount(3, $cargo->fresh()->trabajadores);
    }

    public function test_no_se_puede_borrar_un_cargo_que_tiene_trabajadores(): void
    {
        $cargo = Cargo::factory()->create();
        Trabajador::factory()->create(['cargo_id' => $cargo->id]);

        $this->expectException(QueryException::class);

        $cargo->delete();
    }

    public function test_el_codigo_de_trabajador_es_unico(): void
    {
        Trabajador::factory()->create(['codigo' => 'TRB-0001']);

        $this->expectException(QueryException::class);

        Trabajador::factory()->create(['codigo' => 'TRB-0001']);
    }
}
