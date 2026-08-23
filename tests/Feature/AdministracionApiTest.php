<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\QrCobro;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Administración desde la app: QR de cobro, usuarios y roles.
 *
 * Lo que más se comprueba son las **guardas**: nadie puede desactivarse ni
 * borrarse a sí mismo, no se puede dejar el sistema sin administrador y el rol
 * `admin` no se toca.
 */
class AdministracionApiTest extends TestCase
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

    // ---- QR de cobro --------------------------------------------------------

    public function test_registra_un_qr_con_su_imagen(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin());

        $this->post('/api/v1/qrs-cobro', [
            'nombre' => 'QR mostrador',
            'banco' => 'Banco Unión',
            'fecha_limite' => now()->addMonths(6)->toDateString(),
            'imagen' => UploadedFile::fake()->image('qr.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $qr = QrCobro::firstOrFail();

        Storage::disk('public')->assertExists($qr->imagen);
        $this->assertTrue($qr->activo);
    }

    public function test_al_crear_un_qr_la_imagen_es_obligatoria(): void
    {
        Sanctum::actingAs($this->admin());

        // Sin imagen no hay nada que enseñarle al cliente.
        $this->postJson('/api/v1/qrs-cobro', [
            'nombre' => 'QR sin imagen',
            'fecha_limite' => now()->addMonth()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('imagen');
    }

    public function test_al_editar_un_qr_no_subir_imagen_conserva_la_que_tenia(): void
    {
        Storage::fake('public');

        $qr = QrCobro::factory()->create([
            'imagen' => UploadedFile::fake()->image('viejo.png')->store('qrs-cobro', 'public'),
        ]);
        $imagen = $qr->imagen;

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/qrs-cobro/{$qr->id}", [
            'nombre' => 'Nombre corregido',
            'fecha_limite' => $qr->fecha_limite->toDateString(),
        ])->assertOk();

        $this->assertSame($imagen, $qr->fresh()->imagen);
        Storage::disk('public')->assertExists($imagen);
    }

    public function test_el_listado_marca_cuales_estan_vigentes(): void
    {
        QrCobro::factory()->create([
            'activo' => true,
            'fecha_limite' => now()->addMonth(),
        ]);
        QrCobro::factory()->create([
            'activo' => true,
            'fecha_limite' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/qrs-cobro')->assertOk();

        // `vigente` resuelve la misma condición que usa el POS: sin él la app
        // tendría que recalcular una regla que ya vive en el modelo.
        $vigentes = collect($respuesta->json('data'))->pluck('vigente');

        $this->assertTrue($vigentes->contains(true));
        $this->assertTrue($vigentes->contains(false));
    }

    public function test_archivar_un_qr_no_borra_su_imagen(): void
    {
        Storage::fake('public');

        $qr = QrCobro::factory()->create([
            'imagen' => UploadedFile::fake()->image('qr.png')->store('qrs-cobro', 'public'),
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/qrs-cobro/{$qr->id}")->assertOk();

        // Las ventas cobradas con él conservan su respaldo y lo referencian.
        $this->assertSoftDeleted('qrs_cobro', ['id' => $qr->id]);
        Storage::disk('public')->assertExists($qr->imagen);
    }

    public function test_un_vendedor_ve_los_qr_pero_no_los_administra(): void
    {
        $qr = QrCobro::factory()->create();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        // Ver, sí: los muestra en el mostrador. Administrarlos, no.
        $this->getJson('/api/v1/qrs-cobro')->assertOk();
        $this->postJson('/api/v1/qrs-cobro', ['nombre' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/qrs-cobro/{$qr->id}")->assertForbidden();
    }

    // ---- Usuarios -----------------------------------------------------------

    public function test_crea_una_cuenta_con_su_rol(): void
    {
        $persona = Persona::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/usuarios', [
            'name' => 'jperez',
            'email' => 'jperez@test.com',
            'password' => 'Secreta1234',
            'roles' => ['vendedor'],
            'persona_id' => $persona->id,
        ])->assertCreated()->assertJsonPath('data.roles.0', 'vendedor');

        $this->assertDatabaseHas('users', ['email' => 'jperez@test.com']);
    }

    public function test_una_persona_no_puede_tener_dos_cuentas(): void
    {
        $persona = Persona::factory()->create();
        User::factory()->create(['persona_id' => $persona->id]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/usuarios', [
            'name' => 'otra',
            'email' => 'otra@test.com',
            'password' => 'Secreta1234',
            'roles' => ['vendedor'],
            'persona_id' => $persona->id,
        ])->assertStatus(422)->assertJsonValidationErrors('persona_id');
    }

    public function test_al_editar_una_cuenta_la_contrasena_vacia_no_la_cambia(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncRoles('vendedor');
        $anterior = $usuario->password;

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/usuarios/{$usuario->id}", [
            'name' => 'nombre-nuevo',
            'email' => $usuario->email,
            'roles' => ['vendedor'],
            'persona_id' => $usuario->persona_id,
        ])->assertOk();

        // Vacía significa «no cambiarla», no «dejarla en blanco».
        $this->assertSame($anterior, $usuario->fresh()->password);
        $this->assertSame('nombre-nuevo', $usuario->fresh()->name);
    }

    public function test_la_contrasena_nunca_viaja_de_vuelta(): void
    {
        $persona = Persona::factory()->create();

        Sanctum::actingAs($this->admin());

        $respuesta = $this->postJson('/api/v1/usuarios', [
            'name' => 'jperez',
            'email' => 'jperez@test.com',
            'password' => 'Secreta1234',
            'roles' => ['vendedor'],
            'persona_id' => $persona->id,
        ])->assertCreated();

        $this->assertArrayNotHasKey('password', $respuesta->json('data'));
    }

    public function test_nadie_puede_desactivar_su_propia_cuenta(): void
    {
        $yo = $this->admin();

        Sanctum::actingAs($yo);

        // Se quedaría fuera del sistema en el acto.
        $this->postJson("/api/v1/usuarios/{$yo->id}/estado")->assertStatus(422);

        $this->assertTrue((bool) $yo->fresh()->is_active);
    }

    public function test_nadie_puede_eliminar_su_propia_cuenta(): void
    {
        $yo = $this->admin();

        Sanctum::actingAs($yo);

        $this->deleteJson("/api/v1/usuarios/{$yo->id}")->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $yo->id]);
    }

    public function test_no_se_puede_eliminar_al_unico_administrador(): void
    {
        $unico = $this->admin();
        $otro = User::factory()->create(['is_active' => true])->syncRoles('admin');

        // Ahora hay dos: se borra uno y queda el otro como único.
        Sanctum::actingAs($otro);
        $this->deleteJson("/api/v1/usuarios/{$unico->id}")->assertOk();

        // Y ese último ya no se puede borrar: dejaría el sistema sin nadie que
        // pueda gestionar usuarios ni permisos.
        $tercero = User::factory()->create(['is_active' => true])->syncRoles('supervisor');
        $tercero->givePermissionTo('usuarios.eliminar');

        Sanctum::actingAs($tercero->fresh());
        $this->deleteJson("/api/v1/usuarios/{$otro->id}")->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $otro->id]);
    }

    public function test_no_se_le_quita_el_rol_al_unico_administrador(): void
    {
        $unico = $this->admin();
        $otro = User::factory()->create(['is_active' => true])->syncRoles('supervisor');
        $otro->givePermissionTo('usuarios.editar');

        Sanctum::actingAs($otro->fresh());

        // Quitarle el rol es tan definitivo como borrar la cuenta.
        $this->postJson("/api/v1/usuarios/{$unico->id}", [
            'name' => $unico->name,
            'email' => $unico->email,
            'roles' => ['vendedor'],
            'persona_id' => $unico->persona_id,
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        $this->assertTrue($unico->fresh()->hasRole('admin'));
    }

    public function test_las_personas_vinculables_excluyen_a_quien_ya_tiene_cuenta(): void
    {
        $conCuenta = Persona::factory()->create(['nombres' => 'Marisol']);
        User::factory()->create(['persona_id' => $conCuenta->id]);
        $sinCuenta = Persona::factory()->create(['nombres' => 'Marisol']);

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/usuarios/personas?termino=Marisol')->assertOk();

        $ids = collect($respuesta->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($sinCuenta->id));
        $this->assertFalse($ids->contains($conCuenta->id));
    }

    // ---- Roles --------------------------------------------------------------

    public function test_crea_un_rol_y_le_sincroniza_permisos(): void
    {
        Sanctum::actingAs($this->admin());

        $respuesta = $this->postJson('/api/v1/roles', ['nombre' => 'cajero'])
            ->assertCreated();

        $id = $respuesta->json('data.id');

        $this->postJson("/api/v1/roles/{$id}/permisos", [
            'permisos' => ['ventas.ver', 'ventas.crear', 'inventado.falso'],
        ])->assertOk();

        // Los nombres llegan del cliente: solo se sincronizan los que existen.
        $rol = Role::findById($id, 'web');
        $this->assertSame(['ventas.crear', 'ventas.ver'], $rol->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_el_rol_admin_esta_protegido(): void
    {
        $admin = Role::findByName('admin');

        Sanctum::actingAs($this->admin());

        // Tiene acceso total por Gate::before: editarlo o tocarle los permisos
        // solo confundiría, y borrarlo dejaría el sistema sin administración.
        $this->postJson("/api/v1/roles/{$admin->id}", ['nombre' => 'otro'])->assertStatus(422);
        $this->postJson("/api/v1/roles/{$admin->id}/permisos", ['permisos' => []])->assertStatus(422);
        $this->deleteJson("/api/v1/roles/{$admin->id}")->assertStatus(422);

        $this->assertSame('admin', $admin->fresh()->name);
    }

    public function test_no_se_elimina_un_rol_que_alguien_tiene_asignado(): void
    {
        $rol = Role::findByName('vendedor');
        User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        Sanctum::actingAs($this->admin());

        // Dejaría a esos usuarios sin ningún permiso, sin que nadie se entere
        // hasta que intenten entrar a algo.
        $this->deleteJson("/api/v1/roles/{$rol->id}")->assertStatus(422);

        $this->assertNotNull(Role::findById($rol->id, 'web'));
    }

    public function test_los_permisos_se_devuelven_agrupados_por_modulo(): void
    {
        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/roles/permisos')->assertOk();

        $modulos = collect($respuesta->json('data'))->pluck('modulo');

        // En una lista plana de casi cien permisos no se encuentra nada.
        $this->assertTrue($modulos->contains('ventas'));
        $this->assertNotEmpty($respuesta->json('data.0.permisos'));
    }

    public function test_un_vendedor_no_puede_administrar_usuarios_ni_roles(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        $this->getJson('/api/v1/usuarios')->assertForbidden();
        $this->getJson('/api/v1/roles')->assertForbidden();
        $this->postJson('/api/v1/usuarios', ['name' => 'x'])->assertForbidden();
        $this->postJson('/api/v1/roles', ['nombre' => 'x'])->assertForbidden();
    }
}
