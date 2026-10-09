<?php

namespace App\Support;

use App\Models\Compra;
use App\Models\PrecioProducto;
use App\Models\Producto;
use App\Models\Unidad;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Precios de venta por jornada.
 *
 * El precio de un producto no es fijo: el proveedor sube, la competencia baja.
 * Por eso se fija al empezar el día y queda en el historial (`precios_producto`).
 * **El último registrado es el que ofrece el punto de venta**; el precio que
 * trae cada unidad queda solo como respaldo.
 *
 * El precio del día tiene que quedar por encima del costo: se compara contra el
 * **mayor** costo de las unidades en stock, así ninguna pieza se vende por
 * debajo de lo que costó.
 */
class PreciosDelDia
{
    /**
     * Cache por petición: el POS pide el precio de cada unidad del carrito y no
     * tiene sentido consultar la misma tabla veinte veces.
     *
     * @var array<int, float>
     */
    private array $vigentes = [];

    /**
     * Precio vigente de un producto: el último registrado o, si nunca se
     * registró ninguno, el precio con el que se dio de alta el producto.
     */
    public function precioVigente(int $productoId): float
    {
        if (array_key_exists($productoId, $this->vigentes)) {
            return $this->vigentes[$productoId];
        }

        $precio = PrecioProducto::query()
            ->where('producto_id', $productoId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('precio_venta');

        if ($precio === null) {
            $precio = (float) (Producto::query()->whereKey($productoId)->value('precio_venta') ?? 0);
        }

        return $this->vigentes[$productoId] = (float) $precio;
    }

    /**
     * Último precio registrado ANTES de la fecha dada. Es el que se enseña como
     * referencia al fijar el de hoy; null si nunca se registró.
     */
    public function anterior(int $productoId, CarbonInterface $fecha): ?float
    {
        $precio = PrecioProducto::query()
            ->where('producto_id', $productoId)
            ->whereDate('fecha', '<', $fecha)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('precio_venta');

        return $precio === null ? null : (float) $precio;
    }

    /** ¿Ya se fijaron los precios de la fecha? */
    public function definidos(?CarbonInterface $fecha = null): bool
    {
        return PrecioProducto::query()->whereDate('fecha', $fecha ?? now())->exists();
    }

    /** Cuántos productos con stock siguen sin precio para la fecha. */
    public function pendientes(?CarbonInterface $fecha = null): int
    {
        $fecha ??= now();

        $conPrecio = PrecioProducto::query()
            ->whereDate('fecha', $fecha)
            ->pluck('producto_id')
            ->all();

        return $this->conStock()
            ->reject(fn (Producto $p): bool => in_array($p->id, $conPrecio, true))
            ->count();
    }

    /**
     * ¿Se puede empezar a vender? La jornada arranca fijando los precios: hasta
     * que no se guardan los del día, el punto de venta no cobra. Si no hay nada
     * con stock que fijar, no hay nada que esperar.
     */
    public function listos(?CarbonInterface $fecha = null): bool
    {
        $fecha ??= now();

        return $this->definidos($fecha) || $this->pendientes($fecha) === 0;
    }

    /**
     * Productos con stock, con su precio de referencia (el de la jornada
     * anterior o, si no hay, el inicial), el de hoy si ya se fijó y el costo.
     *
     * @return Collection<int, object>
     */
    public function paraRevisar(?CarbonInterface $fecha = null): Collection
    {
        $fecha ??= now();

        $hoy = PrecioProducto::query()
            ->whereDate('fecha', $fecha)
            ->get()
            ->keyBy('producto_id');

        return $this->conStock()->map(function (Producto $producto) use ($fecha, $hoy): object {
            $registradoHoy = $hoy->get($producto->id);
            // Lo que se enseña para decidir: la jornada anterior o el inicial.
            $anterior = $this->anterior($producto->id, $fecha) ?? (float) $producto->precio_venta;
            $costo = $this->costoReferencia($producto);

            return (object) [
                'producto' => $producto,
                'precio_inicial' => (float) $producto->precio_venta,
                'precio_anterior' => $anterior,
                'precio_hoy' => $registradoHoy === null ? null : (float) $registradoHoy->precio_venta,
                'costo' => $costo,
                'disponibles' => (int) ($producto->disponibles ?? 0),
                'sugerencia' => $this->sugerencia($producto, $anterior, $costo, $fecha),
            ];
        });
    }

    /**
     * Sugerencia de precio por una compra nueva.
     *
     * Cuando entra mercadería de un producto con otro costo, al día siguiente
     * se propone mover el precio en la misma proporción que el costo: así se
     * mantiene el margen con el que se venía vendiendo. Una licuadora que costó
     * 800 y se vende a 1100 y vuelve a comprarse a 850 sugiere
     * 1100 × 850 / 800 = 1168,75 → **1169**.
     *
     * Solo es una propuesta: el precio no cambia hasta que alguien la aplica y
     * confirma la jornada. Mientras tanto se vende al precio vigente.
     *
     * Cuenta la compra recibida **desde el día de la última confirmación y
     * hasta ayer**: lo recibido hoy se sugiere mañana, y lo que ya estaba en
     * stock cuando se confirmó una jornada anterior no se vuelve a sugerir.
     * Se compara contra el lote anterior del mismo producto; sin lote anterior
     * (unidades dadas de alta a mano), contra el costo con el que se confirmó.
     *
     * @param  float  $precioBase  El precio vigente antes de hoy.
     * @param  float  $costoMaximo  Mayor costo en stock: el sugerido tiene que superarlo.
     */
    public function sugerencia(Producto $producto, float $precioBase, float $costoMaximo, ?CarbonInterface $fecha = null): ?object
    {
        $fecha ??= now();

        $base = PrecioProducto::query()
            ->where('producto_id', $producto->id)
            ->whereDate('fecha', '<', $fecha)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        // Un lote = las unidades de una línea de compra. Las tandas de una
        // recepción por partes son el mismo lote: mismo costo.
        $lotes = Unidad::query()
            ->where('producto_id', $producto->id)
            ->whereNotNull('compra_detalle_id')
            ->where('ingresado_en', '<', $fecha->copy()->startOfDay())
            ->selectRaw('compra_detalle_id, compra_id, MAX(ingresado_en) as recibido_en, AVG(costo_unitario) as costo')
            ->groupBy('compra_detalle_id', 'compra_id')
            ->orderByDesc('recibido_en')
            ->limit(2)
            ->get();

        $nuevo = $lotes->first();

        if ($nuevo === null) {
            return null;
        }

        $recibidoEn = Carbon::parse($nuevo->recibido_en);

        if ($base !== null && $recibidoEn->lt($base->fecha->copy()->startOfDay())) {
            return null;
        }

        $costoNuevo = round((float) $nuevo->costo, 2);
        $costoAnterior = round((float) ($lotes->get(1)?->costo ?? $base?->costo_referencia ?? 0), 2);

        // Sin costo con el que comparar, o sin margen que conservar, no hay
        // proporción que aplicar.
        if ($costoAnterior <= 0 || $precioBase <= $costoAnterior) {
            return null;
        }

        $variacion = ($costoNuevo - $costoAnterior) / $costoAnterior;

        // Medio por ciento es ruido del prorrateo, no un cambio de costo.
        if (abs($variacion) < 0.005) {
            return null;
        }

        // Al Bs entero y hacia arriba: el margen nunca queda por debajo del de
        // antes por un redondeo.
        $sugerido = (float) ceil(round($precioBase * $costoNuevo / $costoAnterior, 2));

        // Ya está en ese precio (otra tanda del mismo lote) o el sugerido no
        // cubriría un aparato más caro que sigue en stock: nada que proponer.
        if (abs($sugerido - $precioBase) < 1 || $sugerido <= $costoMaximo) {
            return null;
        }

        return (object) [
            'tipo' => $sugerido > $precioBase ? 'sube' : 'baja',
            'precio_sugerido' => $sugerido,
            'precio_base' => $precioBase,
            'costo_anterior' => $costoAnterior,
            'costo_nuevo' => $costoNuevo,
            'variacion_costo' => round($variacion * 100, 1),
            'compra_codigo' => Compra::query()->whereKey($nuevo->compra_id)->value('codigo'),
            'recibida_en' => $recibidoEn->toDateString(),
        ];
    }

    /**
     * Guarda los precios de la fecha. Una fila por producto y fecha: reenviar
     * el mismo día corrige la del día en vez de duplicarla.
     *
     * @param  array<int|string, float|string>  $precios  producto_id => precio
     */
    public function guardar(array $precios, int $userId, ?CarbonInterface $fecha = null): int
    {
        $fecha ??= now();
        $guardados = 0;

        DB::transaction(function () use ($precios, $userId, $fecha, &$guardados): void {
            foreach ($precios as $productoId => $precio) {
                $producto = Producto::find($productoId);

                if ($producto === null) {
                    continue;
                }

                $valor = round((float) $precio, 2);

                if ($valor <= 0) {
                    continue;
                }

                PrecioProducto::updateOrCreate(
                    ['producto_id' => $producto->id, 'fecha' => $fecha->toDateString()],
                    [
                        'user_id' => $userId,
                        'precio_venta' => $valor,
                        'costo_referencia' => $this->costoReferencia($producto),
                    ],
                );

                $guardados++;
            }
        });

        // El cache de esta petición deja de valer en cuanto se guardan precios.
        $this->vigentes = [];

        return $guardados;
    }

    /**
     * Costo de referencia: el mayor costo entre las unidades en stock del
     * producto. El precio del día tiene que quedar por encima.
     */
    public function costoReferencia(Producto $producto): float
    {
        $maximo = Unidad::query()
            ->where('producto_id', $producto->id)
            ->disponibles()
            ->max('costo_unitario');

        return (float) ($maximo ?? 0);
    }

    /**
     * Productos activos con al menos una unidad disponible.
     *
     * @return Collection<int, Producto>
     */
    private function conStock(): Collection
    {
        return Producto::query()
            ->activos()
            // La categoría se usa para situar cada fila en pantalla: se carga
            // aquí y no al pintar, que con la carga diferida desactivada
            // revienta con LazyLoadingViolationException.
            ->with('categoria')
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Producto $p): bool => ($p->disponibles ?? 0) > 0)
            ->values();
    }
}
