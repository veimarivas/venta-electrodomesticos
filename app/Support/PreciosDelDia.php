<?php

namespace App\Support;

use App\Models\PrecioProducto;
use App\Models\Producto;
use App\Models\Unidad;
use Carbon\CarbonInterface;
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

            return (object) [
                'producto' => $producto,
                'precio_inicial' => (float) $producto->precio_venta,
                // Lo que se enseña para decidir: la jornada anterior o el inicial.
                'precio_anterior' => $this->anterior($producto->id, $fecha) ?? (float) $producto->precio_venta,
                'precio_hoy' => $registradoHoy === null ? null : (float) $registradoHoy->precio_venta,
                'costo' => $this->costoReferencia($producto),
                'disponibles' => (int) ($producto->disponibles ?? 0),
            ];
        });
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
