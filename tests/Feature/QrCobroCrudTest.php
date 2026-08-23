<?php

namespace Tests\Feature;

use App\Livewire\QrsCobro\Index as QrsIndex;
use App\Models\QrCobro;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QrCobroCrudTest extends TestCase
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

    public function test_registra_un_qr_con_su_imagen_y_su_fecha_limite(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(QrsIndex::class)
            ->call('abrirCrear')
            ->set('nombre', 'QR mostrador')
            ->set('banco', 'Banco Unión')
            ->set('imagen', UploadedFile::fake()->image('qr.png'))
            ->set('fechaLimite', now()->addMonth()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-qr');

        $qr = QrCobro::firstOrFail();

        $this->assertSame('QR mostrador', $qr->nombre);
        $this->assertTrue($qr->esta_vigente);
        Storage::disk('public')->assertExists($qr->imagen);
    }

    public function test_la_imagen_es_obligatoria_al_registrar(): void
    {
        Livewire::actingAs($this->admin())
            ->test(QrsIndex::class)
            ->call('abrirCrear')
            ->set('nombre', 'QR sin imagen')
            ->set('fechaLimite', now()->addMonth()->toDateString())
            ->call('guardar')
            ->assertHasErrors('imagen');

        $this->assertSame(0, QrCobro::count());
    }

    public function test_un_qr_caducado_deja_de_estar_vigente(): void
    {
        $qr = QrCobro::factory()->caducado()->create();

        $this->assertFalse($qr->esta_vigente);
        $this->assertSame(0, QrCobro::vigentes()->count());
    }

    public function test_el_dia_de_la_fecha_limite_todavia_vale(): void
    {
        // El banco lo acepta hasta el cierre de ese día.
        QrCobro::factory()->create(['fecha_limite' => now()->toDateString()]);

        $this->assertSame(1, QrCobro::vigentes()->count());
    }

    public function test_archivar_conserva_el_qr_para_el_historico(): void
    {
        $qr = QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(QrsIndex::class)
            ->call('confirmarEliminar', $qr->id)
            ->call('eliminar')
            ->assertDispatched('cerrar-modal-eliminar-qr');

        $this->assertSoftDeleted($qr);
    }

    public function test_un_vendedor_no_puede_registrar_qr(): void
    {
        // Ver, sí: los muestra en el mostrador. Registrarlos, no.
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get(route('ventas.qrs-cobro.index'))->assertOk();

        Livewire::actingAs($vendedor)
            ->test(QrsIndex::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
