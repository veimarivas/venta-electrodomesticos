<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Producto;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\ArqueoDeCaja;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Arqueo de caja: cuadrar el efectivo del turno contra lo que dicen las ventas.
 *
 * La gracia está en comparar **dos números calculados por caminos distintos**:
 * lo contado a mano y lo que suman las ventas. Lo delicado es qué cuenta como
 * efectivo — no todo lo que la venta guarda en `monto_efectivo` entró al cajón.
 */
class ArqueoDeCajaTest extends TestCase
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

    private function arqueo(): ArqueoDeCaja
    {
        return app(ArqueoDeCaja::class);
    }

    /** Una unidad lista para vender a [$precio]. */
    private function unidad(float $precio = 1000): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                // Fijo: la factoría lo pone al azar y dispararía el aviso de
                // stock bajo, que aquí no pinta nada.
                'stock_minimo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cabecera
     */
    private function vender(User $cajero, float $precio = 1000, array $cabecera = []): Venta
    {
        return app(RegistroDeVenta::class)->registrar(
            lineas: [[
                'unidad_id' => $this->unidad($precio)->id,
                'precio_unitario' => $precio,
                'descuento' => 0,
            ]],
            cabecera: $cabecera === [] ? ['metodo_pago' => 'efectivo'] : $cabecera,
            userId: $cajero->id,
        );
    }

    // ---- Abrir --------------------------------------------------------------

    public function test_abre_un_turno_con_su_fondo(): void
    {
        $cajero = $this->cajero();

        $caja = $this->arqueo()->abrir($cajero->id, 200);

        $this->assertTrue($caja->esta_abierta);
        $this->assertSame('200.00', $caja->monto_inicial);
        $this->assertSame($cajero->id, $caja->abierta_por);
    }

    public function test_no_se_pueden_tener_dos_cajas_abiertas(): void
    {
        $cajero = $this->cajero();
        $this->arqueo()->abrir($cajero->id, 100);

        // Con dos abiertas, ninguna cuadraría: las ventas irían a una u otra
        // según el orden en que se consultaran.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya hay una caja abierta');

        $this->arqueo()->abrir($cajero->id, 100);
    }

    public function test_el_fondo_no_puede_ser_negativo(): void
    {
        $this->expectException(RuntimeException::class);

        $this->arqueo()->abrir($this->cajero()->id, -50);
    }

    // ---- Las ventas se atan al turno ---------------------------------------

    public function test_la_venta_se_ata_a_la_caja_abierta(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);

        $venta = $this->vender($cajero);

        $this->assertSame($caja->id, $venta->caja_id);
    }

    public function test_sin_caja_abierta_la_venta_se_registra_igual(): void
    {
        // El sistema tiene que seguir vendiendo aunque nadie haya abierto caja:
        // el arqueo es una ayuda, no un peaje en el mostrador.
        $venta = $this->vender($this->cajero());

        $this->assertNull($venta->caja_id);
        $this->assertTrue($venta->esta_completada);
    }

    // ---- Qué cuenta como efectivo ------------------------------------------

    public function test_suma_las_ventas_en_efectivo(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 200);

        $this->vender($cajero, 1000);
        $this->vender($cajero, 500);

        // 200 de fondo + 1500 cobrados.
        $this->assertSame(170000, $this->arqueo()->esperadoEnCentavos($caja));
    }

    public function test_de_un_pago_mixto_solo_cuenta_la_parte_en_efectivo(): void
    {
        Storage::fake('public');

        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);
        $qr = QrCobro::factory()->create();

        $this->vender($cajero, 1000, [
            'metodo_pago' => 'mixto',
            'qr_cobro_id' => $qr->id,
            'monto_efectivo' => 400,
            'monto_qr' => 600,
            'comprobante_qr' => UploadedFile::fake()
                ->image('respaldo.jpg')
                ->store('comprobantes', 'public'),
        ]);

        // Los 600 del QR fueron al banco, no al cajón.
        $this->assertSame(40000, $this->arqueo()->efectivoCobradoEnCentavos($caja));
    }

    public function test_una_venta_por_qr_no_suma_al_cajon(): void
    {
        Storage::fake('public');

        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);
        $qr = QrCobro::factory()->create();

        $this->vender($cajero, 1000, [
            'metodo_pago' => 'qr',
            'qr_cobro_id' => $qr->id,
            'comprobante_qr' => UploadedFile::fake()
                ->image('respaldo.jpg')
                ->store('comprobantes', 'public'),
        ]);

        $this->assertSame(0, $this->arqueo()->efectivoCobradoEnCentavos($caja));
    }

    public function test_una_venta_con_tarjeta_no_cuenta_como_efectivo(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);

        // `tarjeta` está retirada del mostrador pero viva en el histórico, y su
        // `monto_efectivo` guarda el TOTAL porque el reparto solo separa el
        // dinero cuando hay QR. Fiarse de esa columna inventaría un faltante
        // del importe entero cada vez.
        $venta = $this->vender($cajero);
        $venta->update(['metodo_pago' => 'tarjeta']);

        $this->assertSame('1000.00', $venta->fresh()->monto_efectivo);
        $this->assertSame(0, $this->arqueo()->efectivoCobradoEnCentavos($caja->fresh()));
    }

    public function test_una_venta_anulada_deja_de_contar(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);

        $venta = $this->vender($cajero, 1000);
        $this->vender($cajero, 500);

        app(RegistroDeVenta::class)->anular($venta, 'Error de cobro');

        $this->assertSame(50000, $this->arqueo()->efectivoCobradoEnCentavos($caja->fresh()));
    }

    // ---- Cerrar -------------------------------------------------------------

    public function test_un_cierre_que_cuadra(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 200);
        $this->vender($cajero, 1000);

        $cerrada = $this->arqueo()->cerrar($caja, $cajero->id, 1200);

        $this->assertTrue($cerrada->cuadra);
        $this->assertSame('0.00', $cerrada->diferencia);
        $this->assertSame('1200.00', $cerrada->monto_esperado);
        $this->assertSame('cerrada', $cerrada->estado);
    }

    public function test_un_faltante_sale_en_negativo(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 200);
        $this->vender($cajero, 1000);

        $cerrada = $this->arqueo()->cerrar($caja, $cajero->id, 1150);

        $this->assertTrue($cerrada->falta);
        $this->assertSame('-50.00', $cerrada->diferencia);
    }

    public function test_un_sobrante_sale_en_positivo(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 200);
        $this->vender($cajero, 1000);

        $cerrada = $this->arqueo()->cerrar($caja, $cajero->id, 1230);

        $this->assertTrue($cerrada->sobra);
        $this->assertSame('30.00', $cerrada->diferencia);
    }

    public function test_no_se_cierra_dos_veces(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);
        $this->arqueo()->cerrar($caja, $cajero->id, 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya estaba cerrada');

        $this->arqueo()->cerrar($caja->fresh(), $cajero->id, 0);
    }

    public function test_el_arqueo_no_se_mueve_si_luego_se_anula_una_venta(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);
        $venta = $this->vender($cajero, 1000);

        $cerrada = $this->arqueo()->cerrar($caja, $cajero->id, 1000);

        app(RegistroDeVenta::class)->anular($venta, 'Se anuló al día siguiente');

        // El arqueo tiene que seguir diciendo lo que se vio esa noche: es
        // justo lo que lo hace útil para detectar faltantes.
        $this->assertSame('1000.00', $cerrada->fresh()->monto_esperado);
        $this->assertSame('0.00', $cerrada->fresh()->diferencia);
    }

    public function test_tras_cerrar_se_puede_abrir_otro_turno(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 100);
        $this->arqueo()->cerrar($caja, $cajero->id, 100);

        $segunda = $this->arqueo()->abrir($cajero->id, 100);

        $this->assertTrue($segunda->esta_abierta);
        $this->assertSame(2, Caja::count());
    }

    // ---- Ventas sueltas -----------------------------------------------------

    public function test_una_venta_sin_caja_dentro_del_turno_se_avisa(): void
    {
        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);

        $this->vender($cajero, 1000);

        // Una venta en efectivo que perdió su vínculo con el turno: no suma
        // sola —un arqueo que se inventa de dónde salió el dinero deja de
        // detectar faltantes— pero se enseña para que alguien lo mire.
        $suelta = $this->vender($cajero, 500);
        $suelta->update(['caja_id' => null]);

        $this->assertSame(100000, $this->arqueo()->efectivoCobradoEnCentavos($caja));
        $this->assertSame(1, $this->arqueo()->ventasSueltas($caja));
    }

    public function test_una_venta_anterior_al_turno_no_se_avisa(): void
    {
        $cajero = $this->cajero();

        // Vendida ayer, sin caja: no es un descuadre de este turno.
        $anterior = $this->vender($cajero, 500);
        $anterior->update(['caja_id' => null, 'vendida_en' => now()->subDay()]);

        $caja = $this->arqueo()->abrir($cajero->id, 0);

        $this->assertSame(0, $this->arqueo()->ventasSueltas($caja));
    }

    public function test_una_venta_por_qr_suelta_no_se_avisa(): void
    {
        Storage::fake('public');

        $cajero = $this->cajero();
        $caja = $this->arqueo()->abrir($cajero->id, 0);
        $qr = QrCobro::factory()->create();

        // El aviso es sobre EFECTIVO descuadrado. Una venta por QR no toca el
        // cajón, así que no tenerla atada no descuadra nada.
        $venta = $this->vender($cajero, 1000, [
            'metodo_pago' => 'qr',
            'qr_cobro_id' => $qr->id,
            'comprobante_qr' => UploadedFile::fake()
                ->image('respaldo.jpg')
                ->store('comprobantes', 'public'),
        ]);
        $venta->update(['caja_id' => null]);

        $this->assertSame(0, $this->arqueo()->ventasSueltas($caja));
    }
}
