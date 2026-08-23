<?php

namespace Database\Seeders;

use App\Events\VentaRegistrada;
use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\CuentaDeTrabajador;
use App\Support\GeneradorCodigoCliente;
use App\Support\GeneradorCodigoCompra;
use App\Support\GeneradorCodigoTrabajador;
use App\Support\RecepcionDeCompra;
use App\Support\RegistroDeVenta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Datos de demostración: un año de operación de la tienda.
 *
 * No forma parte de DatabaseSeeder. Se corre a mano, y solo cuando se quiere
 * una base con historia para enseñar el sistema o revisar los reportes:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * **Nada se inventa a mano.** Las compras se recepcionan con
 * `RecepcionDeCompra` y las ventas se registran con `RegistroDeVenta`, los
 * mismos servicios que usa la aplicación. Si se insertaran las filas
 * directamente, la demo mostraría números que el sistema real nunca
 * produciría: costos sin prorratear, unidades sin kardex y ganancias que no
 * cuadran. Es lento a propósito.
 */
class DemoSeeder extends Seeder
{
    /**
     * Cuántos meses de historia se generan hacia atrás desde hoy.
     *
     * Es pública para poder acotarla desde un test: doce meses de operación
     * tardan minutos, y la prueba solo necesita comprobar que lo generado
     * cuadra.
     */
    public int $meses = 12;

    /** Ventas por mes (se sortea dentro de este rango). */
    private const VENTAS_POR_MES = [8, 18];

    /** De cada cuántas ventas, una se anula. */
    private const UNA_ANULADA_CADA = 15;

    private const PROVEEDORES = [
        ['nombre' => 'Importadora Andina S.R.L.', 'nit' => '1023456789', 'contacto' => 'Marcela Quiroga'],
        ['nombre' => 'Distribuidora El Alto Ltda.', 'nit' => '2087654321', 'contacto' => 'Rubén Mamani'],
        ['nombre' => 'TecnoBolivia Import', 'nit' => '3011223344', 'contacto' => 'Silvia Rocha'],
        ['nombre' => 'Comercial Santa Cruz', 'nit' => '4099887766', 'contacto' => 'Iván Zeballos'],
    ];

    private const VENDEDORES = [
        ['nombres' => 'Lucía', 'paterno' => 'Fernández', 'materno' => 'Ortiz', 'carnet' => '7412580'],
        ['nombres' => 'Marco Antonio', 'paterno' => 'Villarroel', 'materno' => 'Paz', 'carnet' => '6398741'],
        ['nombres' => 'Daniela', 'paterno' => 'Salazar', 'materno' => 'Kuno', 'carnet' => '8125479'],
    ];

    private const CLIENTES = [
        ['nombres' => 'Jorge', 'paterno' => 'Aramayo', 'materno' => 'Cruz'],
        ['nombres' => 'Patricia', 'paterno' => 'Choque', 'materno' => 'Rivera'],
        ['nombres' => 'Alberto', 'paterno' => 'Montaño', 'materno' => null],
        ['nombres' => 'Rosa María', 'paterno' => 'Ledezma', 'materno' => 'Vargas'],
        ['nombres' => 'Freddy', 'paterno' => 'Callisaya', 'materno' => 'Nina'],
        ['nombres' => 'Carla', 'paterno' => 'Peñaranda', 'materno' => 'Soliz'],
        ['nombres' => 'Hernán', 'paterno' => 'Guzmán', 'materno' => 'Terán'],
        ['nombres' => 'Verónica', 'paterno' => 'Ticona', 'materno' => 'Apaza'],
        ['nombres' => 'Ramiro', 'paterno' => 'Encinas', 'materno' => 'Loayza'],
        ['nombres' => 'Elena', 'paterno' => 'Suárez', 'materno' => 'Bejarano'],
        ['nombres' => 'Gonzalo', 'paterno' => 'Arispe', 'materno' => 'Machicado'],
        ['nombres' => 'Miriam', 'paterno' => 'Colque', 'materno' => 'Huanca'],
    ];

    /**
     * Repetidos a propósito: el efectivo pesa más que el resto, como en una
     * tienda de barrio. Con un reparto uniforme, el gráfico de métodos de pago
     * saldría en cuatro porciones iguales y no enseñaría nada.
     *
     * No está `credito`: el plan lo preveía, pero el enum de `ventas` no lo
     * admite porque la venta a crédito nunca se implementó.
     */
    private const METODOS_PAGO = ['efectivo', 'efectivo', 'efectivo', 'efectivo', 'qr', 'qr', 'mixto', 'tarjeta', 'transferencia'];

    /** QR de cobro de la demo, para las ventas que pasan por el banco. */
    private ?QrCobro $qrDemo = null;

    /**
     * El hoy de verdad.
     *
     * Se guarda al empezar porque el seeder viaja en el tiempo con
     * `Carbon::setTestNow()` para que los códigos, el kardex y las fechas de
     * venta salgan del periodo que se está generando. Mientras dura ese viaje
     * `now()` devuelve la fecha falsa, así que calcular los meses con `now()`
     * haría que cada mes se contase desde el anterior y toda la historia se
     * apilara en unos pocos días.
     */
    private Carbon $hoy;

    public function __construct(
        private readonly GeneradorCodigoCompra $codigoCompra,
        private readonly GeneradorCodigoCliente $codigoCliente,
        private readonly GeneradorCodigoTrabajador $codigoTrabajador,
        private readonly CuentaDeTrabajador $cuentas,
        private readonly RecepcionDeCompra $recepcion,
        private readonly RegistroDeVenta $ventas,
    ) {}

    public function run(): void
    {
        $this->comprobarQueSePuedeCorrer();

        $this->hoy = now();

        // Misma semilla siempre: dos personas que corran la demo ven las
        // mismas cifras y pueden compararlas. Sin esto, cada captura de
        // pantalla del manual quedaría obsoleta al regenerar la base.
        fake()->seed(20260816);

        // El broadcast a Reverb y el push al administrador no pintan nada
        // aquí: retrasarían el seeder y llenarían la bandeja con avisos de
        // ventas que ocurrieron "hace ocho meses".
        Event::fake([VentaRegistrada::class]);

        $productos = Producto::where('activo', true)->get();

        if ($productos->isEmpty()) {
            throw new RuntimeException(
                'No hay productos. Corre antes: php artisan db:seed'
            );
        }

        $proveedores = $this->crearProveedores();
        $vendedores = $this->crearVendedores();
        $clientes = $this->crearClientes();
        $this->qrDemo = $this->crearQrDeCobro();

        $this->command?->info('Generando '.$this->meses.' meses de operación…');

        $anuladasCada = 0;

        // Se avanza mes a mes, del más antiguo a hoy: primero llega la
        // mercadería y después se vende. Al revés no habría stock que vender.
        for ($atras = $this->meses - 1; $atras >= 0; $atras--) {
            $mes = $this->hoy->copy()->subMonths($atras)->startOfMonth();

            $this->comprarEn($mes, $proveedores, $productos);
            $anuladasCada = $this->venderEn($mes, $vendedores, $clientes, $anuladasCada);
        }

        Carbon::setTestNow();

        $this->resumen();
    }

    /**
     * La demo escribe cientos de filas y no es idempotente: correrla dos veces
     * duplicaría la historia. Se para antes de tocar nada.
     */
    private function comprobarQueSePuedeCorrer(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder no se corre en producción: mezclaría datos inventados con los reales.'
            );
        }

        if (Venta::exists() || Compra::exists()) {
            throw new RuntimeException(
                'Ya hay compras o ventas registradas. Vacía la base antes: php artisan migrate:fresh --seed'
            );
        }
    }

    /** @return Collection<int, Proveedor> */
    private function crearProveedores(): Collection
    {
        return collect(self::PROVEEDORES)->map(fn (array $datos) => Proveedor::create([
            'nombre' => $datos['nombre'],
            'nit' => $datos['nit'],
            'contacto' => $datos['contacto'],
            'telefono' => (string) fake()->numberBetween(60000000, 79999999),
            'correo' => fake()->unique()->companyEmail(),
            'direccion' => fake()->streetAddress(),
            'activo' => true,
        ]));
    }

    /**
     * Vendedores con ficha laboral y cuenta de acceso, por el mismo camino que
     * el módulo de trabajadores: persona → trabajador → usuario.
     *
     * @return Collection<int, User>
     */
    private function crearVendedores(): Collection
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'Vendedor']);

        $usuarios = collect(self::VENDEDORES)->map(function (array $datos) use ($cargo): User {
            $persona = Persona::create([
                'carnet' => $datos['carnet'],
                'nombres' => $datos['nombres'],
                'apellido_paterno' => $datos['paterno'],
                'apellido_materno' => $datos['materno'],
                'celular' => (string) fake()->numberBetween(60000000, 79999999),
                'direccion' => fake()->streetAddress(),
                'correo' => fake()->unique()->safeEmail(),
                'fecha_nacimiento' => fake()->dateTimeBetween('-50 years', '-22 years')->format('Y-m-d'),
            ]);

            $this->codigoTrabajador->crearCon([
                'persona_id' => $persona->id,
                'cargo_id' => $cargo->id,
                'fecha_ingreso' => now()->subYears(2)->toDateString(),
            ]);

            return $this->cuentas->crear($persona, 'vendedor');
        });

        // El admin también vende: si todas las ventas fueran de los tres
        // vendedores, el reporte por vendedor no mostraría nunca la cuenta con
        // la que se entra a la demo.
        $admin = User::where('email', 'admin@electronicahogar.test')->first();

        return $admin ? $usuarios->push($admin) : $usuarios;
    }

    /** @return Collection<int, Cliente> */
    private function crearClientes(): Collection
    {
        return collect(self::CLIENTES)->map(function (array $datos): Cliente {
            $persona = Persona::create([
                'carnet' => (string) fake()->unique()->numberBetween(1000000, 9999999),
                'nombres' => $datos['nombres'],
                'apellido_paterno' => $datos['paterno'],
                'apellido_materno' => $datos['materno'],
                'celular' => (string) fake()->numberBetween(60000000, 79999999),
                'direccion' => fake()->streetAddress(),
                'correo' => fake()->boolean(70) ? fake()->unique()->safeEmail() : null,
            ]);

            return $this->codigoCliente->crearCon(['persona_id' => $persona->id]);
        });
    }

    /**
     * Una o dos compras del mes, recepcionadas por el camino normal para que
     * cada unidad nazca con su costo real y su entrada en el kardex.
     *
     * @param  Collection<int, Proveedor>  $proveedores
     * @param  Collection<int, Producto>  $productos
     */
    private function comprarEn(Carbon $mes, $proveedores, $productos): void
    {
        foreach (range(1, fake()->numberBetween(1, 2)) as $ignorado) {
            $fecha = $mes->copy()->addDays(fake()->numberBetween(0, 9))->setTime(9, 0);

            // La mercadería del mes en curso no puede llegar mañana.
            if ($fecha->greaterThan($this->hoy)) {
                $fecha = $this->hoy->copy();
            }

            Carbon::setTestNow($fecha);

            $lineas = $productos->random(min(fake()->numberBetween(2, 4), $productos->count()));

            $compra = $this->codigoCompra->crearCon([
                'proveedor_id' => $proveedores->random()->id,
                'user_id' => User::where('email', 'admin@electronicahogar.test')->value('id') ?? User::value('id'),
                'numero_factura' => (string) fake()->numberBetween(100000, 999999),
                'fecha_compra' => $fecha->toDateString(),
                'estado' => 'borrador',
                'moneda' => 'BOB',
                'tipo_cambio' => 1,
            ]);

            $subtotal = 0;

            foreach ($lineas as $producto) {
                $cantidad = fake()->numberBetween(3, 12);

                // El costo ronda el 55-70 % del precio de lista: el margen de
                // la demo tiene que parecerse al de una tienda de verdad, si
                // no los reportes de rentabilidad no dicen nada.
                $costoUnitario = round((float) $producto->precio_venta * fake()->randomFloat(2, 0.55, 0.70), 2);
                $subtotalLinea = round($costoUnitario * $cantidad, 2);

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotalLinea,
                    'precio_venta' => $producto->precio_venta,
                ]);

                $subtotal += $subtotalLinea;
            }

            $flete = round($subtotal * fake()->randomFloat(3, 0.01, 0.03), 2);

            $compra->update([
                'subtotal' => $subtotal,
                'flete' => $flete,
                'total' => $subtotal + $flete,
            ]);

            $this->recepcion->recepcionar($compra->refresh());
        }
    }

    /**
     * Las ventas del mes, repartidas por sus días y registradas con el mismo
     * servicio que usa el mostrador.
     *
     * @param  Collection<int, User>  $vendedores
     * @param  Collection<int, Cliente>  $clientes
     * @return int El contador de anulaciones, para seguir contando el mes siguiente
     */
    private function venderEn(Carbon $mes, $vendedores, $clientes, int $contador): int
    {
        $cuantas = fake()->numberBetween(...self::VENTAS_POR_MES);

        // En el mes en curso no se vende más allá de hoy: una venta con fecha
        // futura descuadraría los totales de "este mes" del dashboard.
        $ultimoDia = $this->hoy->isSameMonth($mes)
            ? $this->hoy->day
            : $mes->copy()->endOfMonth()->day;

        foreach (range(1, $cuantas) as $ignorado) {
            $fecha = $mes->copy()
                ->addDays(fake()->numberBetween(0, $ultimoDia - 1))
                ->setTime(fake()->numberBetween(9, 20), fake()->numberBetween(0, 59));

            if ($fecha->greaterThan($this->hoy)) {
                continue;
            }

            Carbon::setTestNow($fecha);

            // Se venden unidades que ya estaban en el almacén en esa fecha:
            // vender un aparato antes de haberlo comprado dejaría el kardex
            // contando al revés.
            $disponibles = Unidad::with('producto')
                ->where('estado', 'en_stock')
                ->where('ingresado_en', '<=', $fecha)
                ->inRandomOrder()
                ->limit(fake()->numberBetween(1, 3))
                ->get();

            if ($disponibles->isEmpty()) {
                continue;
            }

            $lineas = $disponibles->map(fn (Unidad $unidad) => [
                'unidad_id' => $unidad->id,
                'precio_unitario' => (string) $unidad->precio_venta,
                // Un descuento ocasional: sin ellos, precio cobrado y precio
                // de lista serían siempre el mismo número y la columna de
                // descuentos de los reportes saldría vacía. Nunca por encima
                // del tope del producto, que es lo que el mostrador permite.
                'descuento' => fake()->boolean(20)
                    ? (string) min(
                        round((float) $unidad->precio_venta * fake()->randomFloat(2, 0.03, 0.10), 2),
                        (float) ($unidad->producto?->descuento_maximo ?? 0)
                    )
                    : '0',
            ])->all();

            $total = collect($lineas)->sum(
                fn (array $linea): float => (float) $linea['precio_unitario'] - (float) $linea['descuento']
            );

            $venta = $this->ventas->registrar($lineas, [
                'cliente_id' => fake()->boolean(60) ? $clientes->random()->id : null,
                ...$this->cobroDemo(fake()->randomElement(self::METODOS_PAGO), $total),
            ], $vendedores->random()->id);

            $contador++;

            if ($contador % self::UNA_ANULADA_CADA === 0) {
                $devolucion = $fecha->copy()->addDay();

                Carbon::setTestNow($devolucion->greaterThan($this->hoy) ? $this->hoy : $devolucion);

                $this->ventas->anular($venta, 'El cliente devolvió el aparato dentro del plazo.');
            }
        }

        return $contador;
    }

    /**
     * QR de cobro de la tienda de demostración. La fecha límite se pone en el
     * futuro para que el punto de venta lo ofrezca al abrir la demo.
     */
    private function crearQrDeCobro(): QrCobro
    {
        return QrCobro::firstOrCreate(
            ['nombre' => 'QR tienda central'],
            [
                'banco' => 'Banco Unión',
                'titular' => 'Electrónica del Hogar S.R.L.',
                // La imagen no existe en el disco: la demo no incluye
                // archivos, y el POS mostraría un hueco donde va el QR. Es
                // suficiente para ver el flujo completo de cobro.
                'imagen' => 'qrs-cobro/demo.png',
                'fecha_limite' => $this->hoy->copy()->addMonths(6)->toDateString(),
                'activo' => true,
            ]
        );
    }

    /**
     * Datos de cobro de una venta de la demo según su método.
     *
     * @return array<string, mixed>
     */
    private function cobroDemo(string $metodo, float $total): array
    {
        if (! in_array($metodo, Venta::METODOS_CON_QR, true)) {
            return ['metodo_pago' => $metodo];
        }

        // El mixto reparte el total en dos: una parte redonda en efectivo y el
        // resto por QR, como paga alguien que no llega con el billete justo.
        $efectivo = $metodo === 'mixto'
            ? min(floor($total / 100) * 50, $total)
            : 0;

        return [
            'metodo_pago' => $metodo,
            'qr_cobro_id' => $this->qrDemo?->id,
            'comprobante_qr' => 'comprobantes-qr/demo.png',
            'monto_efectivo' => (string) $efectivo,
            'monto_qr' => (string) round($total - $efectivo, 2),
        ];
    }

    private function resumen(): void
    {
        $this->command?->info(sprintf(
            'Demo lista: %d compras, %d unidades, %d ventas (%d anuladas), %d clientes.',
            Compra::count(),
            Unidad::count(),
            Venta::count(),
            Venta::where('estado', 'anulada')->count(),
            Cliente::count(),
        ));
    }
}
