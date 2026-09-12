<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Support\Vitrina;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Escaparate público del catálogo.
 *
 * Es la cara de tienda del sistema y **no pide sesión**: alguien con el enlace
 * entra a mirar los productos. La cara interna es la Vitrina del panel
 * (`/catalogo/vitrina`), que sí exige permiso.
 *
 * Comparte la lógica de «qué es recomendado» con la API y el panel
 * (`App\Support\Vitrina`), así que el ranking se toca en un solo sitio. Lo que
 * **nunca** sale de aquí es el costo ni la ganancia: al público solo se le
 * enseña el precio de venta y cuántas unidades hay.
 */
class StorefrontController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $marcaId = $request->filled('marca') ? (int) $request->query('marca') : null;
        $soloDisponibles = $request->boolean('disponible');
        $categoria = $this->categoriaDesde($request->query('categoria'));

        $hayFiltros = $buscar !== ''
            || $categoria !== null
            || $marcaId !== null
            || $soloDisponibles;

        // Lo que se puede elegir en la barra de filtros: solo categorías y
        // marcas con productos activos, para no ofrecer un filtro que no
        // devolvería nada.
        $categoriasFiltro = Categoria::query()
            ->activas()
            ->withCount(['productos' => fn ($q) => $q->activos()])
            ->ordenadas()
            ->get()
            ->filter(fn (Categoria $c): bool => $c->productos_count > 0)
            ->values();

        $marcasFiltro = Marca::query()
            ->activas()
            ->withCount(['productos' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Marca $m): bool => $m->productos_count > 0)
            ->values();

        // Cifra del hero: cuántos productos activos hay en total. No es la suma
        // de los conteos por categoría —un producto cuelga de una sola—, así
        // que se cuenta aparte.
        $totalProductos = Producto::query()->activos()->count();

        if ($hayFiltros) {
            $productos = $this->consulta($buscar, $categoria, $marcaId, $soloDisponibles)
                ->orderBy('nombre')
                ->paginate(24)
                ->withQueryString();

            return view('tienda.index', [
                'categoriasFiltro' => $categoriasFiltro,
                'marcasFiltro' => $marcasFiltro,
                'buscar' => $buscar,
                'categoria' => $categoria,
                'marcaId' => $marcaId,
                'soloDisponibles' => $soloDisponibles,
                'hayFiltros' => true,
                'productos' => $productos,
                'recomendados' => collect(),
                'secciones' => collect(),
                'totalProductos' => $totalProductos,
            ]);
        }

        // Sin filtros, la portada: los más vendidos y el catálogo por
        // categorías, igual que la Vitrina.
        $datos = Vitrina::datos();

        return view('tienda.index', [
            'categoriasFiltro' => $categoriasFiltro,
            'marcasFiltro' => $marcasFiltro,
            'buscar' => $buscar,
            'categoria' => null,
            'marcaId' => null,
            'soloDisponibles' => false,
            'hayFiltros' => false,
            'productos' => null,
            'recomendados' => $datos['recomendados'],
            'secciones' => $datos['categorias'],
            'totalProductos' => $totalProductos,
        ]);
    }

    public function producto(Producto $producto): View
    {
        // Un producto archivado o de una categoría oculta no existe para el
        // público, aunque alguien tenga el enlace guardado.
        abort_unless($producto->activo, 404);

        $producto->load(['categoria', 'marca', 'especificaciones']);
        $producto->loadCount(['unidades as disponibles' => fn ($q) => $q->disponibles()]);

        if ($producto->categoria && ! $producto->categoria->activo) {
            abort(404);
        }

        $relacionados = Producto::query()
            ->activos()
            ->whereKeyNot($producto->id)
            ->when(
                $producto->categoria_id,
                fn (Builder $q) => $q->where('categoria_id', $producto->categoria_id)
            )
            ->with(['marca'])
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->orderBy('nombre')
            ->limit(4)
            ->get();

        return view('tienda.producto', compact('producto', 'relacionados'));
    }

    /**
     * Filtros comunes del listado público: solo activos, con su categoría y su
     * marca, y el conteo de unidades disponibles para pintar la disponibilidad.
     */
    private function consulta(
        string $buscar,
        ?Categoria $categoria,
        ?int $marcaId,
        bool $soloDisponibles,
    ): Builder {
        return Producto::query()
            ->activos()
            ->buscar($buscar)
            ->with(['categoria', 'marca'])
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->when(
                $categoria !== null,
                fn (Builder $q) => $q->whereIn(
                    'categoria_id',
                    [$categoria->id, ...$categoria->descendientesIds()]
                )
            )
            ->when($marcaId !== null, fn (Builder $q) => $q->where('marca_id', $marcaId))
            ->when(
                $soloDisponibles,
                fn (Builder $q) => $q->whereHas('unidades', fn ($u) => $u->disponibles())
            );
    }

    /**
     * Resuelve la categoría de la URL por su slug (o por id como respaldo) y
     * devuelve null si no existe o está oculta.
     */
    private function categoriaDesde(mixed $valor): ?Categoria
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $valor = trim($valor);

        return Categoria::query()
            ->activas()
            ->where(fn (Builder $q) => $q
                ->where('slug', $valor)
                ->orWhere('id', (int) $valor))
            ->first();
    }
}
