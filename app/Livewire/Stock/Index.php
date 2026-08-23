<?php

namespace App\Livewire\Stock;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Stock actual del catálogo: unidades físicas disponibles por producto,
 * agrupadas por categorías o por marcas, con filtros laterales al estilo
 * de la plantilla de productos de Velzon.
 */
class Index extends Component
{
    /** Vista de agrupación activa: 'categorias' o 'marcas'. */
    public string $vista = 'categorias';

    public string $buscar = '';

    /** Categoría elegida en el filtro lateral; acota a su rama completa. */
    public ?int $categoriaFiltro = null;

    /** @var array<int, int> Marcas seleccionadas en el filtro lateral. */
    public array $marcasFiltro = [];

    /** Filtro interno de la lista de marcas del panel lateral. */
    public string $buscarMarca = '';

    /** 'todos' | 'con_stock' | 'agotados' | 'bajo_minimo' */
    public string $filtroEstado = 'todos';

    /**
     * Grupos contraídos del contenido: clave => true. Las claves son
     * "cat-{id}" (o "cat-sin") para categorías y "marca-{id}" (o "marca-sin").
     *
     * @var array<string, bool>
     */
    public array $colapsadas = [];

    public function cambiarVista(string $vista): void
    {
        if (in_array($vista, ['categorias', 'marcas'], true)) {
            $this->vista = $vista;
        }
    }

    public function cambiarCategoria(int $id): void
    {
        $this->categoriaFiltro = $this->categoriaFiltro === $id ? null : $id;
    }

    public function toggleMarca(int $id): void
    {
        if (in_array($id, $this->marcasFiltro, true)) {
            $this->marcasFiltro = array_values(array_diff($this->marcasFiltro, [$id]));
        } else {
            $this->marcasFiltro[] = $id;
        }
    }

    public function setEstado(string $estado): void
    {
        $this->filtroEstado = $this->filtroEstado === $estado ? 'todos' : $estado;
    }

    /**
     * Abre o contrae un grupo de productos (categoría o marca) en el contenido.
     */
    public function toggleGrupo(string $clave): void
    {
        if (isset($this->colapsadas[$clave])) {
            unset($this->colapsadas[$clave]);
        } else {
            $this->colapsadas[$clave] = true;
        }
    }

    /**
     * Abre el listado de unidades físicas de un producto. El producto viaja
     * por sesión (nunca en la URL) y el módulo de unidades lo consume al montar.
     */
    public function verUnidades(int $id): void
    {
        abort_unless(auth()->user()?->can('unidades.ver') ?? false, 403);

        session()->put('producto_activo', $id);

        $this->redirect(route('inventario.unidades.index'));
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'categoriaFiltro', 'marcasFiltro', 'buscarMarca', 'filtroEstado', 'colapsadas']);
    }

    /**
     * Consulta de productos activos con su conteo de unidades en stock.
     * Los filtros de estado se resuelven con HAVING sobre el alias que
     * genera withCount: "disponibles" es un agregado, no una columna.
     */
    private function consultaProductos(): Builder
    {
        $termino = trim($this->buscar);

        return Producto::query()
            ->activos()
            ->with(['categoria', 'marca'])
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->when($this->categoriaFiltro !== null, fn ($q) => $q->whereIn('categoria_id', $this->idsRama()))
            ->when($this->marcasFiltro !== [], fn ($q) => $q->whereIn('marca_id', $this->marcasFiltro))
            ->when($termino !== '', fn ($q) => $q->where(function (Builder $q2) use ($termino) {
                $q2->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('sku', 'like', "%{$termino}%")
                    ->orWhere('modelo', 'like', "%{$termino}%")
                    ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'like', "%{$termino}%"));
            }))
            ->when($this->filtroEstado === 'con_stock', fn ($q) => $q->having('disponibles', '>', 0))
            ->when($this->filtroEstado === 'agotados', fn ($q) => $q->having('disponibles', 0))
            ->orderBy('nombre')
            ->orderBy('id');
    }

    /**
     * Ids de la categoría activa y de toda su descendencia.
     *
     * @return array<int, int>
     */
    private function idsRama(?int $categoriaId = null): array
    {
        $categoriaId ??= $this->categoriaFiltro;

        if ($categoriaId === null) {
            return [];
        }

        $categoria = Categoria::find($categoriaId);

        return $categoria !== null
            ? [$categoria->id, ...$categoria->descendientesIds()]
            : [];
    }

    /**
     * Indicadores agregados de una colección de productos.
     *
     * @return array{unidades: int, productos: int, conStock: int, agotados: int, bajoMinimo: int, valor: float}
     */
    private function resumen(Collection $productos): array
    {
        return [
            'unidades' => (int) $productos->sum('disponibles'),
            'productos' => $productos->count(),
            'conStock' => $productos->where('disponibles', '>', 0)->count(),
            'agotados' => $productos->where('disponibles', 0)->count(),
            'bajoMinimo' => $productos
                ->filter(fn (Producto $p) => $p->disponibles > 0 && $p->disponibles <= $p->stock_minimo)
                ->count(),
            'valor' => (float) $productos->sum(fn (Producto $p) => (float) $p->precio_venta * $p->disponibles),
        ];
    }

    /**
     * Árbol de categorías con productos. Cada nodo suma lo suyo y lo de sus
     * descendientes, para que una rama se lea de un vistazo sin abrirla.
     *
     * @return array<int, array{categoria: Categoria|null, productos: Collection, hijos: array, resumen: array}>
     */
    private function arbolCategorias(Collection $productos): array
    {
        $categorias = Categoria::query()->ordenadas()->get();
        $porPadre = $categorias->groupBy(fn (Categoria $c) => $c->padre_id ?? 0);
        $productosPorCategoria = $productos->groupBy(fn (Producto $p) => $p->categoria_id ?? 0);

        $nodo = function (int $padreId) use ($porPadre, $productosPorCategoria, &$nodo): array {
            $nodos = [];

            foreach ($porPadre->get($padreId, collect()) as $categoria) {
                $directos = $productosPorCategoria->get($categoria->id, collect());
                $hijos = $nodo($categoria->id);

                $todos = $directos->concat(
                    collect($hijos)->flatMap(fn (array $hijo) => $hijo['productos'])
                );

                // Una categoría sin productos propios ni en su descendencia no
                // aporta nada al análisis: se omite. Al filtrar por una rama
                // solo queda visible esa rama, sin grupos vacíos de las demás.
                if ($todos->isEmpty()) {
                    continue;
                }

                $nodos[] = [
                    'categoria' => $categoria,
                    'productos' => $directos->values(),
                    'hijos' => $hijos,
                    'resumen' => $this->resumen($todos),
                ];
            }

            return $nodos;
        };

        return $nodo(0);
    }

    /**
     * Productos agrupados por marca, con su marca (o null) y sus totales.
     * "Sin marca" va al final, no en medio de las demás.
     *
     * @return Collection<int, array{marca: Marca|null, productos: Collection, resumen: array}>
     */
    private function gruposMarca(Collection $productos): Collection
    {
        return $productos
            ->groupBy(fn (Producto $p) => $p->marca_id ?? 'sin-marca')
            ->map(fn (Collection $items, string $marcaId) => [
                'marca' => $marcaId === 'sin-marca' ? null : $items->first()->marca,
                'productos' => $items->values(),
                'resumen' => $this->resumen($items),
            ])
            ->sortBy(fn (array $grupo) => $grupo['marca']?->nombre ?? 'ZZZ Sin marca')
            ->values();
    }

    /**
     * Lista plana del árbol de categorías (pre-orden, con nivel de sangría)
     * para el filtro lateral. Cada fila suma los productos de su rama, igual
     * que el árbol de contenido: el número dice cuántos verás al pulsarla.
     *
     * @param  array<int, int>  $countsDirectos  productos activos por categoria_id
     * @return array<int, array{id: int, nombre: string, nivel: int, total: int, activa: bool}>
     */
    private function listaCategorias(array $countsDirectos): array
    {
        $categorias = Categoria::query()->ordenadas()->get();
        $porPadre = $categorias->groupBy(fn (Categoria $c) => $c->padre_id ?? 0);

        $nodos = [];

        $visitar = function (int $padreId, int $nivel) use ($porPadre, $countsDirectos, &$visitar): array {
            $nodos = [];

            foreach ($porPadre->get($padreId, collect()) as $categoria) {
                $hijos = $visitar($categoria->id, $nivel + 1);

                $total = ($countsDirectos[$categoria->id] ?? 0) + array_sum(array_column($hijos, 'total'));

                $nodos[] = [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'nivel' => $nivel,
                    'total' => $total,
                    'activa' => $this->categoriaFiltro === $categoria->id,
                ];

                array_push($nodos, ...$hijos);
            }

            return $nodos;
        };

        return $visitar(0, 0);
    }

    public function render(): View
    {
        $productos = $this->consultaProductos()->get();

        // "Bajo mínimo" compara un agregado contra una columna (disponibles
        // contra stock_minimo), algo que MariaDB/MySQL rechaza en HAVING por
        // ONLY_FULL_GROUP_BY. Se resuelve en memoria sobre el resultado.
        if ($this->filtroEstado === 'bajo_minimo') {
            $productos = $productos
                ->filter(fn (Producto $p) => $p->disponibles > 0 && $p->disponibles <= $p->stock_minimo)
                ->values();
        }

        $categorias = $this->arbolCategorias($productos);

        // Productos huérfanos: categoría sin asignar o cuyo registro se eliminó.
        // No cuelgan de ningún nodo del árbol, se agrupan aparte para no perderlos.
        $restantes = $productos->filter(
            fn (Producto $p) => $p->categoria_id === null || $p->categoria === null
        );

        if ($restantes->isNotEmpty()) {
            $categorias[] = [
                'categoria' => null,
                'productos' => $restantes->values(),
                'hijos' => [],
                'resumen' => $this->resumen($restantes),
            ];
        }

        // Totales por categoría y marca para el panel de filtros. Se calculan
        // sobre todo el catálogo activo, no sobre el resultado filtrado: así el
        // panel lateral no se encoge mientras se buscan productos.
        $countsDirectos = Producto::query()
            ->activos()
            ->selectRaw('categoria_id, count(*) as total')
            ->whereNotNull('categoria_id')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        $countsMarcas = Producto::query()
            ->activos()
            ->selectRaw('marca_id, count(*) as total')
            ->whereNotNull('marca_id')
            ->groupBy('marca_id')
            ->pluck('total', 'marca_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        $marcasFiltroLista = Marca::query()
            ->activas()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Marca $marca) => [
                'id' => $marca->id,
                'nombre' => $marca->nombre,
                'total' => $countsMarcas[$marca->id] ?? 0,
                'activa' => in_array($marca->id, $this->marcasFiltro, true),
            ])
            ->when($this->buscarMarca !== '', fn ($coleccion) => $coleccion->filter(
                fn (array $marca) => str_contains(mb_strtolower($marca['nombre']), mb_strtolower($this->buscarMarca))
            ))
            ->values();

        return view('livewire.stock.index', [
            'resumen' => $this->resumen($productos),
            'categorias' => $categorias,
            'marcas' => $this->gruposMarca($productos),
            'categoriasFiltro' => $this->listaCategorias($countsDirectos),
            'marcasFiltroLista' => $marcasFiltroLista,
            'buscar' => $this->buscar,
            'vista' => $this->vista,
            'categoriaFiltro' => $this->categoriaFiltro,
            'marcasFiltro' => $this->marcasFiltro,
            'filtroEstado' => $this->filtroEstado,
            'buscarMarca' => $this->buscarMarca,
            'colapsadas' => $this->colapsadas,
        ]);
    }
}