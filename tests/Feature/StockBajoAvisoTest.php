<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\StockBajoPush;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Aviso de stock bajo al vender.
 *
 * El listado de stock bajo ya existía en el panel y en la app, pero había que
 * ir a mirarlo. El momento en que la información sirve es cuando el aparato
 * sale del almacén, que es cuando todavía se puede reponer.
 */
class StockBajoAvisoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function almacenero(): User
    {
        // `supervisor` tiene `stock.ver`, que es el permiso que decide quién
        // recibe el aviso.
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    private function cajero(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    /**
     * Un producto con [$total] unidades en stock y un mínimo de [$minimo].
     *
     * @return array{0: Producto, 1: \Illuminate\Support\Collection<int, Unidad>}
     */
    private function productoCon(int $total, int $minimo): array
    {
        $producto = Producto::factory()->create([
            'stock_minimo' => $minimo,
            'precio_venta' => 1000,
            'activo' => true,
        ]);

        $unidades = collect(range(1, $total))->map(fn (): Unidad => Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => 600,
            'precio_venta' => 1000,
        ]));

        return [$producto, $unidades];
    }

    private function vender(Unidad $unidad, User $usuario): Venta
    {
        return app(RegistroDeVenta::class)->registrar(
            lineas: [[
                'unidad_id' => $unidad->id,
                'precio_unitario' => (float) $unidad->precio_venta,
                'descuento' => 0,
            ]],
            cabecera: ['metodo_pago' => 'efectivo'],
            userId: $usuario->id,
        );
    }

    // ---- Cuándo avisa ------------------------------------------------------

    public function test_vender_la_unidad_que_deja_el_producto_en_su_minimo_avisa(): void
    {
        Notification::fake();

        $almacenero = $this->almacenero();
        // Mínimo 2, hay 3: al vender una quedan 2, que ya es el mínimo.
        [$producto, $unidades] = $this->productoCon(total: 3, minimo: 2);

        $this->vender($unidades[0], $this->cajero());

        Notification::assertSentTo(
            $almacenero,
            StockBajoPush::class,
            fn (StockBajoPush $aviso): bool => $aviso->producto->is($producto)
                && $aviso->disponibles === 2
        );
    }

    public function test_agotarse_avisa_aunque_ya_hubiera_avisado_del_minimo(): void
    {
        $almacenero = $this->almacenero();
        $cajero = $this->cajero();
        // Mínimo 1, hay 2: la primera venta lo deja en el mínimo y avisa.
        [, $unidades] = $this->productoCon(total: 2, minimo: 1);

        $this->vender($unidades[0], $cajero);

        Notification::fake();

        // La segunda lo deja a cero. Quedarse sin nada que vender es más grave
        // que rozar el mínimo, así que tiene su propio umbral: con uno solo,
        // la guarda de «no repetir» se habría tragado este aviso.
        $this->vender($unidades[1], $cajero);

        Notification::assertSentTo(
            $almacenero,
            StockBajoPush::class,
            function (StockBajoPush $aviso) use ($almacenero): bool {
                $datos = $aviso->toDatabase($almacenero);

                // «Se acabó» y «queda poco» son dos urgencias distintas; un
                // aviso que las mezcle obliga a abrirlo para saber cuál es.
                return $aviso->disponibles === 0
                    && $datos['titulo'] === 'Sin stock'
                    && str_contains($datos['cuerpo'], 'sin unidades disponibles');
            }
        );
    }

    public function test_el_aviso_apunta_a_la_ficha_del_producto(): void
    {
        Notification::fake();

        $almacenero = $this->almacenero();
        [$producto, $unidades] = $this->productoCon(total: 2, minimo: 1);

        $this->vender($unidades[0], $this->cajero());

        Notification::assertSentTo(
            $almacenero,
            StockBajoPush::class,
            function (StockBajoPush $aviso) use ($almacenero, $producto): bool {
                $datos = $aviso->toDatabase($almacenero);

                return $datos['tipo'] === 'stock_bajo'
                    && $datos['producto_id'] === $producto->id
                    && $datos['enlace'] === "app://catalogo/productos/{$producto->id}";
            }
        );
    }

    // ---- Cuándo NO avisa ---------------------------------------------------

    public function test_no_vuelve_a_avisar_de_un_producto_que_ya_estaba_bajo_minimo(): void
    {
        $almacenero = $this->almacenero();
        // Mínimo 2, hay 4: la primera venta lo deja en 3 y la segunda en 2,
        // que es cuando cruza. La tercera lo deja en 1: bajo mínimo, pero ya
        // avisado.
        [, $unidades] = $this->productoCon(total: 4, minimo: 2);
        $cajero = $this->cajero();

        $this->vender($unidades[0], $cajero);
        $this->vender($unidades[1], $cajero);

        Notification::fake();

        // Sin la guarda de «no repetir», cada venta de un producto ya bajo
        // mínimo volvería a avisar y a la tercera nadie mira los avisos.
        $this->vender($unidades[2], $cajero);

        // Se comprueba sobre el MISMO almacenero, no sobre uno nuevo:
        // `almacenero()` crea un usuario cada vez que se llama, así que
        // pasárselo aquí recién creado haría pasar la aserción sola.
        // `assertNothingSent` tampoco vale: la venta manda su propio aviso.
        Notification::assertNotSentTo($almacenero, StockBajoPush::class);
    }

    public function test_no_avisa_si_la_venta_no_baja_del_minimo(): void
    {
        Notification::fake();

        $almacenero = $this->almacenero();
        // Mínimo 1, hay 5: al vender una quedan 4, muy por encima.
        [, $unidades] = $this->productoCon(total: 5, minimo: 1);

        $this->vender($unidades[0], $this->cajero());

        Notification::assertNotSentTo($almacenero, StockBajoPush::class);
    }

    public function test_un_minimo_de_cero_significa_que_no_se_controla(): void
    {
        Notification::fake();

        $almacenero = $this->almacenero();
        [, $unidades] = $this->productoCon(total: 1, minimo: 0);

        // Avisar de esto sería ruido en cada venta de cualquier accesorio.
        $this->vender($unidades[0], $this->cajero());

        Notification::assertNotSentTo($almacenero, StockBajoPush::class);
    }

    // ---- A quién avisa -----------------------------------------------------

    public function test_solo_avisa_a_quien_puede_ver_el_stock(): void
    {
        Notification::fake();

        $almacenero = $this->almacenero();
        $sinPermiso = User::factory()->create(['is_active' => true]);

        [, $unidades] = $this->productoCon(total: 2, minimo: 1);

        $this->vender($unidades[0], $this->cajero());

        Notification::assertSentTo($almacenero, StockBajoPush::class);
        Notification::assertNotSentTo($sinPermiso, StockBajoPush::class);
    }

    public function test_una_cuenta_desactivada_no_recibe_avisos(): void
    {
        Notification::fake();

        $inactivo = User::factory()->create(['is_active' => false])->syncRoles('supervisor');

        [, $unidades] = $this->productoCon(total: 2, minimo: 1);

        $this->vender($unidades[0], $this->cajero());

        Notification::assertNotSentTo($inactivo, StockBajoPush::class);
    }

    // ---- El aviso llega al historial que lee la app ------------------------

    public function test_el_aviso_queda_en_el_historial_de_notificaciones(): void
    {
        $almacenero = $this->almacenero();
        [, $unidades] = $this->productoCon(total: 2, minimo: 1);

        $this->vender($unidades[0], $this->cajero());

        // Sin fake: se comprueba que de verdad se escribió en la tabla, que es
        // lo que consume GET /api/v1/notificaciones.
        $aviso = $almacenero->notifications()
            ->get()
            ->first(fn ($n): bool => ($n->data['tipo'] ?? null) === 'stock_bajo');

        $this->assertNotNull($aviso, 'El aviso de stock bajo no llegó al historial.');
        $this->assertSame('Stock bajo', $aviso->data['titulo']);
        $this->assertSame(1, $aviso->data['disponibles']);
    }
}
