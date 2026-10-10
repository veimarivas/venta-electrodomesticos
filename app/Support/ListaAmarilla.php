<?php

namespace App\Support;

use App\Models\Unidad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Lista amarilla: aparatos que llevan demasiado tiempo en la tienda.
 *
 * Cuenta los días **a la fecha de hoy** desde que el aparato entró al stock
 * (`unidades.ingresado_en`: la recepción de la compra o el alta manual). Entra
 * todo lo que sigue físicamente en la tienda —en stock o reservado en un
 * carrito— y lleva al menos `$dias` días. Con el doble del umbral pasa a
 * «crítico»: ahí ya no es un aviso, es dinero parado.
 *
 * Agrupa por producto porque la decisión es por producto (bajar el precio,
 * moverlo a la vitrina, no volver a comprarlo), pero conserva cada aparato
 * para saber cuál es el más viejo.
 */
class ListaAmarilla
{
    public const ATENCION = 'atencion';

    public const CRITICO = 'critico';

    /** Estados de un aparato que sigue en la tienda. */
    private const EN_TIENDA = ['en_stock', 'reservado'];

    public function __construct(private readonly PreciosDelDia $precios) {}

    /**
     * @return array{
     *     dias: int, desde: string, resumen: array<string, mixed>,
     *     productos: list<array<string, mixed>>
     * }
     */
    public function consultar(int $dias, ?string $buscar = null, bool $conCostos = false): array
    {
        $dias = max(1, $dias);
        $hoy = now();
        $limite = $hoy->copy()->subDays($dias)->endOfDay();

        $unidades = Unidad::query()
            ->with(['producto.marca:id,nombre', 'producto.categoria:id,nombre', 'compra:id,codigo,fecha_compra'])
            ->whereIn('estado', self::EN_TIENDA)
            ->whereRaw('COALESCE(ingresado_en, created_at) <= ?', [$limite])
            ->when($buscar !== null && trim($buscar) !== '', fn ($q) => $q->buscar(trim($buscar)))
            ->get();

        $productos = $unidades
            ->groupBy('producto_id')
            ->map(fn (Collection $grupo): array => $this->fila($grupo, $dias, $hoy, $conCostos))
            ->sortByDesc('dias_max')
            ->values()
            ->all();

        $todos = collect($productos);

        return [
            'dias' => $dias,
            'desde' => $limite->toDateString(),
            'resumen' => [
                'productos' => $todos->count(),
                'unidades' => (int) $todos->sum('unidades'),
                'criticos' => $todos->where('nivel', self::CRITICO)->count(),
                'valor_venta' => round((float) $todos->sum('valor_venta'), 2),
                'capital' => $conCostos ? round((float) $todos->sum('capital'), 2) : null,
                'dias_max' => (int) ($todos->max('dias_max') ?? 0),
            ],
            'productos' => $productos,
        ];
    }

    /** Cuántos aparatos están en la lista con el umbral guardado (para avisos). */
    public function cantidad(?int $dias = null): int
    {
        $dias ??= app(Ajustes::class)->listaAmarillaDias();

        return Unidad::query()
            ->whereIn('estado', self::EN_TIENDA)
            ->whereRaw('COALESCE(ingresado_en, created_at) <= ?', [now()->subDays($dias)->endOfDay()])
            ->count();
    }

    /** @param  Collection<int, Unidad>  $grupo */
    private function fila(Collection $grupo, int $dias, Carbon $hoy, bool $conCostos): array
    {
        $producto = $grupo->first()->producto;
        $precio = $this->precios->precioVigente((int) $grupo->first()->producto_id);

        $aparatos = $grupo
            ->map(function (Unidad $u) use ($hoy, $conCostos): array {
                $ingreso = Carbon::parse($u->ingresado_en ?? $u->created_at);

                return [
                    'id' => $u->id,
                    'codigo' => $u->codigo_interno,
                    'serial' => $u->serial,
                    'estado' => $u->estado,
                    'ingresado_en' => $ingreso->toDateString(),
                    'dias' => (int) $ingreso->copy()->startOfDay()->diffInDays($hoy->copy()->startOfDay()),
                    'compra' => $u->compra?->codigo,
                    'costo' => $conCostos ? (float) $u->costo_unitario : null,
                ];
            })
            ->sortByDesc('dias')
            ->values();

        $diasMax = (int) $aparatos->max('dias');

        return [
            'producto_id' => $producto?->id,
            'producto' => $producto?->nombre ?? 'Producto',
            'modelo' => $producto?->modelo,
            'marca' => $producto?->marca?->nombre,
            'categoria' => $producto?->categoria?->nombre,
            'imagen' => $producto?->imagen ? Storage::disk('public')->url($producto->imagen) : null,
            'unidades' => $aparatos->count(),
            'dias_max' => $diasMax,
            'dias_promedio' => (int) round((float) $aparatos->avg('dias')),
            'ingreso_mas_antiguo' => $aparatos->first()['ingresado_en'],
            'nivel' => $diasMax >= $dias * 2 ? self::CRITICO : self::ATENCION,
            'precio' => $precio,
            'valor_venta' => round($precio * $aparatos->count(), 2),
            'capital' => $conCostos ? round((float) $aparatos->sum('costo'), 2) : null,
            'aparatos' => $aparatos->all(),
        ];
    }
}
