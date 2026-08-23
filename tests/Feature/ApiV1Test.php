<?php

namespace Tests\Feature;

use App\Events\VentaRegistrada;
use App\Listeners\AvisarVentaRegistrada;
use App\Models\Dispositivo;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Notifications\VentaRegistradaPush;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['password' => 'password', 'is_active' => true])->syncRoles('admin');
    }

    private function vendedor(): User
    {
        return User::factory()->create(['password' => 'password', 'is_active' => true])->syncRoles('vendedor');
    }

    private function vender(float $precio = 1500, float $costo = 1000, ?User $vendedor = null)
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create(['precio_venta' => $precio])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $precio,
        ]);

        return app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => (string) $precio, 'descuento' => '0']],
            [],
            ($vendedor ?? $this->admin())->id
        );
    }

    // ---- Autenticación -----------------------------------------------------

    public function test_el_login_entrega_un_token_y_el_perfil(): void
    {
        $admin = $this->admin();

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'usuario' => $admin->email,
            'password' => 'password',
            'dispositivo' => 'Pixel de Veimar',
        ])->assertOk();

        $respuesta->assertJsonStructure([
            'token',
            'usuario' => ['id', 'nombre', 'correo', 'roles', 'permisos'],
        ]);

        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_se_puede_entrar_con_el_nombre_de_usuario(): void
    {
        // A los trabajadores se les entrega un usuario tipo "jperezlopez",
        // no un correo: la app tiene que aceptar los dos.
        $usuario = User::factory()->create([
            'name' => 'jperezlopez',
            'password' => 'password',
        ])->syncRoles('vendedor');

        $this->postJson('/api/v1/auth/login', [
            'usuario' => 'jperezlopez',
            'password' => 'password',
            'dispositivo' => 'Moto G',
        ])->assertOk();

        $this->assertSame(1, $usuario->tokens()->count());
    }

    public function test_una_cuenta_bloqueada_no_obtiene_token(): void
    {
        $usuario = User::factory()->create(['password' => 'password', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'usuario' => $usuario->email,
            'password' => 'password',
            'dispositivo' => 'Moto G',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('usuario');

        $this->assertSame(0, $usuario->tokens()->count());
    }

    public function test_una_password_mala_no_revela_si_la_cuenta_existe(): void
    {
        $usuario = User::factory()->create(['password' => 'password']);

        $existe = $this->postJson('/api/v1/auth/login', [
            'usuario' => $usuario->email,
            'password' => 'incorrecta',
            'dispositivo' => 'Moto G',
        ])->assertStatus(422);

        $noExiste = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'nadie@ejemplo.com',
            'password' => 'incorrecta',
            'dispositivo' => 'Moto G',
        ])->assertStatus(422);

        // Mismo mensaje en los dos casos.
        $this->assertSame(
            $existe->json('errors.usuario'),
            $noExiste->json('errors.usuario')
        );
    }

    public function test_volver_a_entrar_desde_el_mismo_dispositivo_reemplaza_su_token(): void
    {
        $admin = $this->admin();

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/auth/login', [
                'usuario' => $admin->email,
                'password' => 'password',
                'dispositivo' => 'Pixel de Veimar',
            ])->assertOk();
        }

        // Un token por dispositivo, no tres acumulados.
        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_el_logout_revoca_solo_el_token_usado(): void
    {
        $admin = $this->admin();

        $tokenA = $admin->createToken('Teléfono A')->plainTextToken;
        $admin->createToken('Teléfono B');

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // El otro teléfono sigue con su sesión abierta.
        $this->assertSame(1, $admin->fresh()->tokens()->count());
        $this->assertSame('Teléfono B', $admin->fresh()->tokens()->first()->name);
    }

    public function test_las_rutas_privadas_exigen_token(): void
    {
        foreach (['/api/v1/auth/perfil', '/api/v1/dashboard/resumen', '/api/v1/ventas'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }

    public function test_el_perfil_no_expone_la_password(): void
    {
        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/auth/perfil')->assertOk();

        $this->assertArrayNotHasKey('password', $respuesta->json('data'));
        $this->assertArrayNotHasKey('two_factor_secret', $respuesta->json('data'));
    }

    // ---- Dispositivos ------------------------------------------------------

    public function test_registra_el_token_del_telefono(): void
    {
        Sanctum::actingAs($admin = $this->admin());

        $this->postJson('/api/v1/dispositivos', [
            'token' => 'fcm-token-abc',
            'plataforma' => 'android',
            'nombre_dispositivo' => 'Pixel 8',
        ])->assertCreated();

        $this->assertDatabaseHas('dispositivos', [
            'token' => 'fcm-token-abc',
            'user_id' => $admin->id,
        ]);
    }

    public function test_registrar_dos_veces_el_mismo_token_no_lo_duplica(): void
    {
        // Firebase emite el token por instalación: si el teléfono cambia de
        // dueño, el token migra de fila en vez de quedar en dos usuarios.
        $primero = $this->admin();
        $segundo = $this->vendedor();

        Sanctum::actingAs($primero);
        $this->postJson('/api/v1/dispositivos', ['token' => 'mismo-token', 'plataforma' => 'android'])
            ->assertCreated();

        Sanctum::actingAs($segundo);
        $this->postJson('/api/v1/dispositivos', ['token' => 'mismo-token', 'plataforma' => 'android'])
            ->assertOk();

        $this->assertSame(1, Dispositivo::where('token', 'mismo-token')->count());
        $this->assertSame($segundo->id, Dispositivo::where('token', 'mismo-token')->first()->user_id);
    }

    public function test_no_se_puede_dar_de_baja_el_dispositivo_de_otro(): void
    {
        // Si no, conociendo un token cualquiera se dejaría a otro sin avisos.
        $ajeno = Dispositivo::factory()->create(['token' => 'token-ajeno']);

        Sanctum::actingAs($this->admin());

        $this->deleteJson('/api/v1/dispositivos/token-ajeno')->assertNotFound();

        $this->assertDatabaseHas('dispositivos', ['id' => $ajeno->id]);
    }

    // ---- Dashboard ---------------------------------------------------------

    public function test_el_resumen_trae_el_comparativo_con_el_periodo_anterior(): void
    {
        $admin = $this->admin();

        $anterior = $this->vender(1000, 500, $admin);
        $anterior->update(['vendida_en' => now()->subDay()]);

        $this->vender(2000, 1000, $admin);

        Sanctum::actingAs($admin);

        $respuesta = $this->getJson('/api/v1/dashboard/resumen?rango=hoy')->assertOk();

        // assertEquals y no assertSame: json_encode(2000.0) sale como 2000 y
        // al decodificar vuelve como entero.
        $this->assertEquals(2000, $respuesta->json('actual.ingreso'));
        $this->assertEquals(1000, $respuesta->json('anterior.ingreso'));
        // Duplicar el ingreso de ayer es +100 %.
        $this->assertEquals(100, $respuesta->json('variacion.ingreso'));
    }

    public function test_sin_base_anterior_la_variacion_es_nula_no_cero(): void
    {
        // Decir «+100 %» partiendo de cero sería inventar una tendencia.
        $admin = $this->admin();
        $this->vender(2000, 1000, $admin);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/dashboard/resumen?rango=hoy')
            ->assertOk()
            ->assertJsonPath('variacion.ingreso', null);
    }

    public function test_un_vendedor_no_ve_ganancias_en_el_dashboard(): void
    {
        // La app la puede tener un vendedor; el margen no es dato suyo.
        $vendedor = $this->vendedor();
        $this->vender(1500, 1000);

        Sanctum::actingAs($vendedor);

        // Ni siquiera entra: el dashboard exige reportes.ver.
        $this->getJson('/api/v1/dashboard/resumen')->assertForbidden();
    }

    public function test_la_grafica_devuelve_la_serie_del_rango(): void
    {
        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/dashboard/grafica?rango=mes')->assertOk();

        $this->assertSame(now()->daysInMonth, count($respuesta->json('serie')));
        $this->assertArrayHasKey('ingreso', $respuesta->json('serie.0'));
    }

    // ---- Ventas ------------------------------------------------------------

    public function test_el_listado_de_ventas_pagina_y_filtra(): void
    {
        $admin = $this->admin();

        foreach (range(1, 3) as $i) {
            $this->vender(1000 + $i, 500, $admin);
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/ventas?por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_el_detalle_de_una_venta_trae_sus_aparatos(): void
    {
        $admin = $this->admin();
        $venta = $this->vender(1500, 1000, $admin);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/ventas/{$venta->id}")
            ->assertOk()
            ->assertJsonPath('data.codigo', $venta->codigo)
            ->assertJsonCount(1, 'data.detalles')
            ->assertJsonStructure(['data' => ['detalles' => [['codigo_interno', 'precio_unitario']]]]);
    }

    public function test_un_vendedor_no_ve_costos_en_el_detalle_de_una_venta(): void
    {
        $admin = $this->admin();
        $venta = $this->vender(1500, 1000, $admin);

        // El vendedor sí puede ver ventas, pero no sus costos.
        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson("/api/v1/ventas/{$venta->id}")->assertOk();

        $this->assertArrayNotHasKey('ganancia', $respuesta->json('data'));
        $this->assertArrayNotHasKey('costo_total', $respuesta->json('data'));
        $this->assertArrayNotHasKey('costo_unitario', $respuesta->json('data.detalles.0'));

        // El admin sí los ve.
        Sanctum::actingAs($admin);
        $respuestaAdmin = $this->getJson("/api/v1/ventas/{$venta->id}")->assertOk();

        $this->assertEquals(500, $respuestaAdmin->json('data.ganancia'));
        $this->assertEquals(1000, $respuestaAdmin->json('data.costo_total'));
    }

    // ---- Reportes ----------------------------------------------------------

    public function test_stock_bajo_lista_los_productos_por_reponer(): void
    {
        $producto = Producto::factory()->create(['activo' => true, 'stock_minimo' => 3]);
        Unidad::factory()->create(['producto_id' => $producto->id, 'estado' => 'en_stock']);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/inventario/stock-bajo')
            ->assertOk()
            ->assertJsonPath('productos.0.id', $producto->id)
            ->assertJsonPath('productos.0.disponibles', 1)
            ->assertJsonPath('productos.0.agotado', false);
    }

    public function test_la_rentabilidad_por_proveedor_exige_ver_costos(): void
    {
        Sanctum::actingAs($this->vendedor());
        $this->getJson('/api/v1/reportes/proveedores')->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/reportes/proveedores')->assertOk();
    }

    // ---- Notificaciones y push ---------------------------------------------

    public function test_una_venta_avisa_a_quien_supervisa(): void
    {
        Notification::fake();

        $supervisor = User::factory()->create(['is_active' => true])->syncRoles('supervisor');
        $vendedor = $this->vendedor();

        (new AvisarVentaRegistrada)->handle(
            new VentaRegistrada($this->vender(1500, 1000, $vendedor))
        );

        Notification::assertSentTo($supervisor, VentaRegistradaPush::class);
    }

    public function test_quien_registro_la_venta_no_se_avisa_a_si_mismo(): void
    {
        Notification::fake();

        $admin = $this->admin();

        (new AvisarVentaRegistrada)->handle(
            new VentaRegistrada($this->vender(1500, 1000, $admin))
        );

        Notification::assertNotSentTo($admin, VentaRegistradaPush::class);
    }

    public function test_una_venta_no_falla_si_el_servidor_de_websockets_esta_caido(): void
    {
        // ShouldBroadcastNow habla con Reverb en la misma petición. Si el
        // servidor está apagado, la excepción llegaría al mostrador y le diría
        // al cajero que la venta falló cuando ya está cobrada y guardada.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            // Puerto donde no escucha nadie.
            'broadcasting.connections.reverb.options.port' => 1,
        ]);

        $venta = $this->vender(1500, 1000);

        $this->assertSame('completada', $venta->fresh()->estado);
        $this->assertDatabaseHas('ventas', ['id' => $venta->id, 'estado' => 'completada']);
    }

    public function test_el_aviso_llega_aunque_falle_el_websocket(): void
    {
        // Laravel emite el broadcast ANTES de correr los oyentes: sin cuidado,
        // una excepción del WebSocket se lleva por delante el push.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
        ]);

        $supervisor = User::factory()->create(['is_active' => true])->syncRoles('supervisor');

        $this->vender(1500, 1000, $this->vendedor());

        $this->assertSame(1, $supervisor->notifications()->count());
    }

    public function test_el_push_no_se_intenta_sin_credenciales_de_firebase(): void
    {
        // Sin Firebase configurado el aviso se guarda igual y no revienta:
        // el canal fcm solo se activa cuando hay credenciales.
        config(['services.fcm.credentials' => null]);

        $admin = $this->admin();
        Dispositivo::factory()->create(['user_id' => $admin->id]);

        $canales = (new VentaRegistradaPush($this->vender(1500, 1000)))->via($admin);

        $this->assertSame(['database'], $canales);
    }

    public function test_el_historial_de_avisos_es_solo_del_usuario(): void
    {
        // No se notifica a mano: al vender, el listener despacha el aviso solo
        // (la cola es 'sync' en pruebas), así que esto recorre el flujo real.
        $admin = $this->admin();
        $otro = $this->vendedor();

        $this->vender(1500, 1000, $otro);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/notificaciones')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.sin_leer', 1)
            ->assertJsonPath('data.0.tipo', 'venta_registrada');

        // El vendedor que la registró no se avisa a sí mismo.
        Sanctum::actingAs($otro);
        $this->getJson('/api/v1/notificaciones')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_marcar_leido_un_aviso_ajeno_no_funciona(): void
    {
        $admin = $this->admin();
        $otro = $this->vendedor();

        $this->vender(1500, 1000, $otro);
        $aviso = $admin->notifications()->first();

        Sanctum::actingAs($otro);
        $this->postJson("/api/v1/notificaciones/{$aviso->id}/leida")->assertNotFound();

        $this->assertNull($aviso->fresh()->read_at);
    }

    // ---- Límite de peticiones ----------------------------------------------

    public function test_el_login_esta_limitado_contra_fuerza_bruta(): void
    {
        $admin = $this->admin();

        // El sexto intento en el mismo minuto se rechaza.
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/login', [
                'usuario' => $admin->email,
                'password' => 'incorrecta',
                'dispositivo' => 'Moto G',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'usuario' => $admin->email,
            'password' => 'password',
            'dispositivo' => 'Moto G',
        ])->assertStatus(429);
    }
}
