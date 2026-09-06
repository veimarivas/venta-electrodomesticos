<?php

namespace App\Support;

use App\Models\Categoria;
use App\Models\Producto;

/**
 * Datos de la vitrina del catálogo: lo que se le enseña a quien llega a mirar
 * —los más vendidos arriba y el catálogo ordenado por categorías—.
 *
 * Lo comparten la API (`GET /api/v1/catalogo/vitrina`) y el panel
 * (`GET /catalogo/vitrina`): si cambia la regla de «qué es recomendado», se
 * toca un solo sitio.
 */
class Vitrina
{
    /**
     * @return array{
     *     recomendados: \Illuminate\Support\Collection<int, \App\Models\Producto>,
     *     categorias: \Illuminate\Support\Collection<int, \App\Models\Categoria>
     * }
     */
    public static function datos(): array
    {
        // Recomendados: los más vendidos del mes, por unidades. El ranking lo
        // calcula Reportes, la misma fuente del panel y de la app.
        $ranking = app(Reportes::class)->topProductos(
            now()->startOfMonth(),
            now()->endOfMonth(),
            10
        );

        $ordenDeIds = $ranking->pluck('id');

        $recomendados = Producto::query()
            ->with(['categoria', 'marca'])
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->whereIn('id', $ordenDeIds)
            ->get()
            // `whereIn` no promete el orden del ranking: se reordena aquí.
            ->sortBy(fn (Producto $p): int => $ordenDeIds->search($p->id))
            ->values();

        // Categorías con sus productos, para la vista «de tienda»: cada sección
        // es una categoría y dentro van sus productos. Se descartan las que se
        // quedaron sin productos activos para no abrir una sección vacía.
        $categorias = Categoria::query()
            ->activas()
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['categoria', 'marca'])
                ->withCount(['unidades as disponibles' => fn ($u) => $u->disponibles()])
                ->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Categoria $c) => $c->productos->isNotEmpty())
            ->values();

        return compact('recomendados', 'categorias');
    }
}