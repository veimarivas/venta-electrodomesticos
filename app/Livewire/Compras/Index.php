<?php

namespace App\Livewire\Compras;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\VentaDetalle;
use App\Support\GeneradorCodigoCompra;
use App\Support\ProrrateoDeGastos;
use App\Support\RecepcionDeCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithPagination;

    /** Livewire usa la paginación de Tailwind si no se le indica el tema. */
    protected string $paginationTheme = 'bootstrap';

    // ---- Listado ----------------------------------------------------------

    public string $buscar = '';

    public string $ordenarPor = 'fecha_compra';

    public string $direccionOrden = 'desc';

    /** todos | borrador | recepcionada | anulada */
    public string $filtroEstado = 'todos';

    // ---- Cabecera de la compra -------------------------------------------

    public ?int $compraId = null;

    public string $proveedor_id = '';

    public string $numero_factura = '';

    public string $fecha_compra = '';

    /**
     * Lo que se pagó al proveedor por la compra completa. Es el único importe
     * de la cabecera: el detalle por producto tiene que sumar exactamente esto.
     */
    public string $total_pagado = '';

    public string $notas = '';

    /**
     * Cuando se llega desde la ficha de un proveedor con ?proveedor={id},
     * el selector de proveedor se bloquea y se fuerza ese proveedor.
     */
    public ?int $proveedorForzado = null;

    /**
     * Productos de la compra, en memoria hasta que se registra.
     *
     * La compra se guarda de una sola vez —cabecera, líneas y unidades— así
     * que las líneas no existen en base mientras se está armando el formulario.
     *
     * @var array<int, array{producto_id: int, cantidad: string, costo_total: string}>
     */
    public array $lineas = [];

    // ---- Línea de detalle -------------------------------------------------

    /** Compra sobre la que se está trabajando el detalle. */
    public ?int $detalleCompraId = null;

    /** Buscador de productos dentro del selector. */
    public string $buscarProducto = '';

    /**
     * Filtros en cascada del selector de producto: primero se acota por
     * categoría, luego por marca. Un catálogo de cientos de productos en un
     * único desplegable es inmanejable cuando se está copiando una factura.
     */
    public ?int $categoriaLinea = null;

    public ?int $marcaLinea = null;

    // ---- Seriales de las unidades generadas -------------------------------

    /**
     * Seriales en edición, indexados por id de unidad: [12 => 'S3X9A2K1', ...].
     *
     * El código interno lo genera el sistema al recepcionar; el serial es el
     * del fabricante y viene en la caja, así que se teclea después, con los
     * aparatos delante.
     *
     * @var array<int, string>
     */
    public array $seriales = [];

    // ---- Acciones sobre la compra ----------------------------------------

    public ?int $eliminarId = null;

    public string $eliminarCodigo = '';

    private const CAMPOS_CABECERA = [
        'proveedor_id', 'numero_factura', 'fecha_compra', 'total_pagado', 'notas', 'lineas',
    ];

    // =======================================================================
    // Validación
    // =======================================================================

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')],
            'numero_factura' => ['nullable', 'string', 'max:60'],
            'fecha_compra' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'total_pagado' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:2000'],

            // Una compra sin productos no genera inventario: no tiene sentido.
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'lineas.*.costo_total' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'proveedor_id.required' => 'Debes elegir un proveedor.',
            'fecha_compra.required' => 'Debes indicar la fecha de la compra.',
            'fecha_compra.before_or_equal' => 'La fecha de compra no puede ser futura.',
            'total_pagado.required' => 'Indica cuánto se pagó por la compra completa.',
            'total_pagado.min' => 'El total pagado debe ser mayor a cero.',
            'lineas.required' => 'Agrega al menos un producto a la compra.',
            'lineas.min' => 'Agrega al menos un producto a la compra.',
            'lineas.*.cantidad.required' => 'Indica cuántas unidades se compraron.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'lineas.*.costo_total.required' => 'Indica cuánto se pagó por este producto.',
            'lineas.*.costo_total.min' => 'Lo pagado por el producto debe ser mayor a cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'proveedor_id' => 'proveedor',
            'numero_factura' => 'número de factura',
            'fecha_compra' => 'fecha de compra',
            'total_pagado' => 'total pagado',
            'lineas' => 'productos',
            'lineas.*.cantidad' => 'cantidad',
            'lineas.*.costo_total' => 'pagado por el producto',
        ];
    }

    public function updated(string $campo): void
    {
        // Las líneas llegan como "lineas.0.cantidad", que no está en la lista
        // pero sí tiene regla con comodín.
        if (in_array($campo, self::CAMPOS_CABECERA, true) || str_starts_with($campo, 'lineas.')) {
            $this->validateOnly($campo, $this->rules());
        }
    }

    /**
     * Desde la ficha del proveedor se puede llegar con «?abrir={id}» para que
     * el detalle de esa compra quede ya abierto al cargar la página.
     * Con «?proveedor={id}» se deja el proveedor bloqueado y se abre
     * automáticamente el modal de nueva compra.
     */
    public function mount(): void
    {
        if (request()->filled('abrir')) {
            $this->abrirDetalle((int) request()->integer('abrir'));
        }

        // Si se llega desde la ficha de un proveedor, preseleccionarlo y abrir el modal.
        if (request()->filled('proveedor')) {
            $proveedorId = (int) request()->integer('proveedor');
            $proveedor = Proveedor::where('activo', true)->find($proveedorId);

            if ($proveedor) {
                $this->proveedorForzado = $proveedor->id;
                $this->abrirCrear();
            }
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatedProveedorId(): void
    {
        if ($this->proveedorForzado !== null) {
            $this->proveedor_id = (string) $this->proveedorForzado;
        }
    }

    #[Computed]
    public function formularioValido(): bool
    {
        return Validator::make(
            $this->only(self::CAMPOS_CABECERA),
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    // ---- Cuadre en tiempo real --------------------------------------------
    //
    // Todo en centavos enteros: comparar decimales en float haría que una
    // compra que cuadra a la perfección apareciera descuadrada por 0,000001.

    /** Suma de lo asignado a los productos, en centavos. */
    #[Computed]
    public function asignadoEnCentavos(): int
    {
        return collect($this->lineas)->sum(function (array $linea): int {
            $importe = $linea['costo_total'] ?? '';

            return is_numeric($importe) ? ProrrateoDeGastos::aCentavos($importe) : 0;
        });
    }

    /** Total pagado de la cabecera, en centavos. */
    #[Computed]
    public function pagadoEnCentavos(): int
    {
        return is_numeric($this->total_pagado)
            ? ProrrateoDeGastos::aCentavos($this->total_pagado)
            : 0;
    }

    /**
     * Lo que falta por repartir entre los productos. Negativo significa que se
     * asignó de más.
     */
    #[Computed]
    public function saldoEnCentavos(): int
    {
        return $this->pagadoEnCentavos - $this->asignadoEnCentavos;
    }

    /**
     * ¿El detalle cuadra con el total pagado? Tiene que ser exacto: una compra
     * cuyo detalle no suma lo pagado deja un costo que nadie carga, y el
     * inventario dejaría de valer lo que realmente costó.
     */
    #[Computed]
    public function cuadra(): bool
    {
        return $this->lineas !== [] && $this->pagadoEnCentavos > 0 && $this->saldoEnCentavos === 0;
    }

    /** ¿Se puede registrar ya la compra? */
    #[Computed]
    public function compraValida(): bool
    {
        return $this->formularioValido && $this->cuadra;
    }

    /**
     * Productos de las líneas, para pintarlas sin una consulta por fila.
     *
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function productosDeLineas(): Collection
    {
        $ids = collect($this->lineas)->pluck('producto_id')->filter()->all();

        return $ids === []
            ? new Collection
            : Producto::with(['marca', 'categoria'])->whereIn('id', $ids)->get()->keyBy('id');
    }

    // =======================================================================
    // Datos para la vista
    // =======================================================================

    /** Solo proveedores activos: los inactivos no deben ensuciar el selector. */
    #[Computed]
    public function proveedores(): Collection
    {
        return Proveedor::where('activo', true)->orderBy('nombre')->get();
    }

    /** Compra cuyo detalle se está editando. */
    #[Computed]
    public function compraEnDetalle(): ?Compra
    {
        return $this->detalleCompraId === null
            ? null
            : Compra::with(['proveedor', 'detalles.producto'])->find($this->detalleCompraId);
    }

    /**
     * Ids de los productos que ya están en la compra: no se pueden repetir en
     * dos líneas (el prorrateo se volvería ambiguo y el índice único de
     * compra_detalles lo rechazaría).
     *
     * @return array<int, int>
     */
    private function productosYaEnLaCompra(): array
    {
        // Las líneas viven en memoria hasta que se registra la compra, así que
        // el "ya está" se mira sobre el formulario, no sobre la base.
        return collect($this->lineas)->pluck('producto_id')->filter()->all();
    }

    /**
     * Ids de la categoría elegida y de toda su descendencia. Elegir
     * «Electrónica» tiene que traer también lo que cuelga de ella, igual que
     * en el listado de productos.
     *
     * @return array<int, int>
     */
    private function ramaCategoriaLinea(): array
    {
        if ($this->categoriaLinea === null) {
            return [];
        }

        $categoria = Categoria::find($this->categoriaLinea);

        return $categoria !== null
            ? [$categoria->id, ...$categoria->descendientesIds()]
            : [];
    }

    /**
     * Consulta base de productos agregables: activos y que aún no estén en la
     * compra. De aquí salen tanto la lista como los selectores de categoría y
     * marca, para que ninguno ofrezca una opción sin resultados.
     */
    private function consultaProductosAgregables(): \Illuminate\Database\Eloquent\Builder
    {
        return Producto::query()
            ->where('activo', true)
            ->whereNotIn('id', $this->productosYaEnLaCompra());
    }

    /**
     * Categorías con productos agregables, en forma de árbol plano.
     *
     * @return array<int, array{id: int, nombre: string, nivel: int}>
     */
    #[Computed]
    public function categoriasLinea(): array
    {
        // Una categoría se ofrece si ella o alguna descendiente tiene producto
        // agregable; por eso se parte de las categorías CON producto y se
        // suben sus ancestros.
        $conProducto = $this->consultaProductosAgregables()
            ->distinct()
            ->pluck('categoria_id')
            ->filter()
            ->all();

        if ($conProducto === []) {
            return [];
        }

        $todas = Categoria::query()->ordenadas()->get()->keyBy('id');
        $visibles = [];

        foreach ($conProducto as $id) {
            $actual = $todas->get($id);
            $saltos = 0;

            while ($actual !== null && $saltos++ < 50) {
                $visibles[$actual->id] = true;
                $actual = $actual->padre_id !== null ? $todas->get($actual->padre_id) : null;
            }
        }

        $porPadre = $todas->groupBy(fn (Categoria $c): int => $c->padre_id ?? 0);
        $opciones = [];

        $recorrer = function (int $padreId, int $nivel) use ($porPadre, &$recorrer, &$opciones, $visibles): void {
            foreach ($porPadre->get($padreId, collect()) as $categoria) {
                if (! isset($visibles[$categoria->id])) {
                    continue;
                }

                $opciones[] = ['id' => $categoria->id, 'nombre' => $categoria->nombre, 'nivel' => $nivel];

                $recorrer($categoria->id, $nivel + 1);
            }
        };

        $recorrer(0, 0);

        return $opciones;
    }

    /**
     * Marcas con productos agregables dentro de la categoría elegida. Sin
     * categoría son todas las que tengan algo que agregar.
     *
     * @return Collection<int, Marca>
     */
    #[Computed]
    public function marcasLinea(): Collection
    {
        $rama = $this->ramaCategoriaLinea();

        $ids = $this->consultaProductosAgregables()
            ->when($rama !== [], fn ($q) => $q->whereIn('categoria_id', $rama))
            ->distinct()
            ->pluck('marca_id')
            ->filter()
            ->all();

        return Marca::query()->whereIn('id', $ids)->orderBy('nombre')->get();
    }

    /**
     * Productos que se pueden agregar, ya filtrados por categoría, marca y
     * término de búsqueda. Llevan el stock actual para decidir cuánto comprar.
     *
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function productosDisponibles(): Collection
    {
        $rama = $this->ramaCategoriaLinea();

        return $this->consultaProductosAgregables()
            ->with(['marca', 'categoria'])
            ->withCount(['unidades as unidades_disponibles' => fn ($q) => $q->disponibles()])
            ->when($rama !== [], fn ($q) => $q->whereIn('categoria_id', $rama))
            ->when($this->marcaLinea !== null, fn ($q) => $q->where('marca_id', $this->marcaLinea))
            ->when($this->buscarProducto !== '', function ($q) {
                $termino = trim($this->buscarProducto);
                $q->where(fn ($sub) => $sub->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('modelo', 'like', "%{$termino}%"));
            })
            ->orderBy('nombre')
            ->limit(50)
            ->get();
    }

    /**
     * Al cambiar de categoría, la marca elegida puede dejar de tener productos
     * ahí: su opción desaparece del selector y la lista saldría vacía sin que
     * se vea por qué.
     */
    public function updatedCategoriaLinea(): void
    {
        if ($this->marcaLinea !== null && ! $this->marcasLinea->contains('id', $this->marcaLinea)) {
            $this->marcaLinea = null;
        }
    }

    // =======================================================================
    // Seriales de las unidades generadas por la compra
    // =======================================================================

    /**
     * Unidades físicas que generó la compra, agrupadas por producto.
     *
     * @return Collection<int, Unidad>
     */
    #[Computed]
    public function unidadesDeLaCompra(): Collection
    {
        if ($this->detalleCompraId === null) {
            return new Collection;
        }

        return Unidad::query()
            ->with('producto')
            ->where('compra_id', $this->detalleCompraId)
            ->orderBy('producto_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Carga los seriales actuales en el formulario y abre el panel. Se abre
     * con lo ya guardado para poder corregir, no solo para llenar en blanco.
     */
    public function abrirSeriales(): void
    {
        $this->autorizar('unidades.editar');

        $compra = $this->compraEnDetalle;

        if ($compra === null || ! $compra->esta_recepcionada) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Las unidades existen recién cuando la compra se recepciona.');

            return;
        }

        unset($this->unidadesDeLaCompra);

        $this->seriales = $this->unidadesDeLaCompra
            ->mapWithKeys(fn (Unidad $u): array => [$u->id => (string) $u->serial])
            ->all();

        $this->resetValidation();
        $this->dispatch('abrir-modal-seriales-compra');
    }

    public function guardarSeriales(): void
    {
        $this->autorizar('unidades.editar');

        $unidades = $this->unidadesDeLaCompra->keyBy('id');

        // Normaliza antes de validar: los vacíos van como NULL porque el
        // índice único de la columna rechazaría dos cadenas vacías.
        $limpios = [];

        foreach ($this->seriales as $unidadId => $serial) {
            $unidadId = (int) $unidadId;
            $serial = trim((string) $serial);

            // Solo se aceptan ids de esta compra: el componente es un endpoint
            // invocable y no debe poder tocar unidades de otra.
            if (! $unidades->has($unidadId)) {
                continue;
            }

            $limpios[$unidadId] = $serial === '' ? null : $serial;
        }

        // Duplicados dentro del propio formulario: la regla `unique` mira la
        // base de datos y no vería dos iguales tecleados en esta misma pasada.
        $repetidos = collect($limpios)->filter()->duplicates();

        if ($repetidos->isNotEmpty()) {
            $this->addError('seriales', 'Hay seriales repetidos en el formulario: '.$repetidos->unique()->implode(', '));

            return;
        }

        foreach ($limpios as $unidadId => $serial) {
            if ($serial === null) {
                continue;
            }

            $yaExiste = Unidad::where('serial', $serial)->whereKeyNot($unidadId)->exists();

            if ($yaExiste) {
                $this->addError('seriales', "El serial «{$serial}» ya está registrado en otra unidad.");

                return;
            }
        }

        DB::transaction(function () use ($limpios): void {
            foreach ($limpios as $unidadId => $serial) {
                Unidad::whereKey($unidadId)->update(['serial' => $serial]);
            }
        });

        unset($this->unidadesDeLaCompra);

        $registrados = collect($limpios)->filter()->count();

        $this->dispatch('cerrar-modal-seriales-compra');
        $this->dispatch('toast', tipo: 'success', mensaje: "Seriales guardados: {$registrados} de ".count($limpios).'.');
    }

    /** Código que le tocaría a la próxima compra. */
    #[Computed]
    public function codigoPrevisto(): string
    {
        return app(GeneradorCodigoCompra::class)->siguiente();
    }

    /**
     * Rentabilidad de la compra en detalle: cuánto se invirtió, cuánto se ha
     * recuperado y cuánto queda por vender.
     *
     * Todo se calcula en centavos enteros sobre las unidades físicas que
     * generó la compra, que es lo que pediste al inicio: "ver las ganancias
     * correspondientes a esa compra".
     *
     * El ingreso realizado sale de venta_detalles (precio realmente cobrado,
     * con su descuento), no de unidades.precio_venta. La ganancia potencial sí
     * usa unidades.precio_venta: lo que queda en stock todavía no se ha
     * vendido, así que su precio de lista es la única estimación disponible.
     *
     * @return array<string, string|int>
     */
    #[Computed]
    public function rentabilidad(): array
    {
        $compra = $this->compraEnDetalle;

        if ($compra === null || ! $compra->esta_recepcionada) {
            return [];
        }

        $unidades = $compra->unidades()->get();
        $vendidas = $unidades->where('estado', 'vendido');
        $enStock = $unidades->where('estado', 'en_stock');

        $centavos = fn ($valor) => ProrrateoDeGastos::aCentavos($valor);

        // El ingreso realizado sale de venta_detalles, no de unidades:
        // precio_unitario menos descuento es lo que realmente se cobró. Solo
        // cuentan las ventas completadas — una anulada no es ingreso.
        $lineasVendidas = VentaDetalle::query()
            ->whereIn('unidad_id', $vendidas->pluck('id'))
            ->whereHas('venta', fn ($v) => $v->where('estado', 'completada'))
            ->get();

        $inversion = $centavos($compra->total);
        $ingreso = (int) $lineasVendidas->sum(
            fn (VentaDetalle $l) => $centavos($l->precio_unitario) - $centavos($l->descuento)
        );
        $costoVendidas = (int) $lineasVendidas->sum(fn (VentaDetalle $l) => $centavos($l->costo_unitario));
        $potencial = (int) $enStock->sum(fn ($i) => $centavos($i->precio_venta) - $centavos($i->costo_unitario));

        return [
            'inversion' => ProrrateoDeGastos::aDecimal($inversion),
            'unidades' => $unidades->count(),
            'vendidas' => $vendidas->count(),
            'en_stock' => $enStock->count(),
            'ingreso' => ProrrateoDeGastos::aDecimal($ingreso),
            'ganancia' => ProrrateoDeGastos::aDecimal($ingreso - $costoVendidas),
            'potencial' => ProrrateoDeGastos::aDecimal($potencial),
            'recuperado' => $inversion > 0 ? round($ingreso / $inversion * 100, 1) : 0,
            'margen' => $ingreso > 0 ? round(($ingreso - $costoVendidas) / $ingreso * 100, 1) : 0,
        ];
    }

    // =======================================================================
    // Cabecera: alta y edición
    // =======================================================================

    public function abrirCrear(): void
    {
        $this->autorizar('compras.crear');

        $this->limpiarCabecera();
        $this->fecha_compra = now()->toDateString();

        // Si hay proveedor forzado por ?proveedor=, mantenerlo seleccionado
        // aunque limpiarCabecera haya reseteado los campos.
        if ($this->proveedorForzado !== null) {
            $this->proveedor_id = (string) $this->proveedorForzado;
        }

        $this->dispatch('abrir-modal-compra');
    }

    // ---- Productos de la compra (en memoria) -------------------------------

    /**
     * Agrega el producto elegido en el selector como una línea nueva.
     * La cantidad arranca en 1 y el importe vacío: son los dos datos que hay
     * que copiar de la factura.
     */
    public function agregarLinea(int $productoId): void
    {
        $this->autorizar('compras.crear');

        $producto = $this->consultaProductosAgregables()->find($productoId);

        if ($producto === null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Ese producto ya está en la compra o no está activo.');

            return;
        }

        $this->lineas[] = [
            'producto_id' => $producto->id,
            'cantidad' => '1',
            'costo_total' => '',
        ];

        // Los filtros del selector se limpian para buscar el siguiente producto
        // desde cero, que es lo que se hace al copiar una factura.
        $this->reset(['buscarProducto', 'categoriaLinea', 'marcaLinea']);
        $this->resetValidation('lineas');
    }

    public function quitarLinea(int $indice): void
    {
        unset($this->lineas[$indice]);

        // Reindexar: con huecos, Livewire deja de casar cada fila con sus
        // inputs y el usuario ve importes en la fila equivocada.
        $this->lineas = array_values($this->lineas);

        $this->resetValidation('lineas');
    }

    /**
     * El precio de venta de la línea NO se pregunta: sale de
     * productos.precio_venta, el precio de lista del catálogo. Este método es
     * el punto único donde se lee, para que no se duplique el criterio.
     */
    private function precioDeVentaDe(Producto $producto): string
    {
        return (string) $producto->precio_venta;
    }

    /**
     * Registra la compra completa y genera sus unidades, todo de una vez.
     *
     * No hay estado intermedio: o queda la compra con su detalle y su
     * inventario, o no queda nada. La generación de unidades se delega en
     * RecepcionDeCompra, que es quien reparte los importes al centavo.
     */
    public function guardar(): void
    {
        $this->autorizar('compras.crear');

        // Si se entró con ?proveedor= el proveedor no se puede cambiar ni
        // siquiera manipulando el input oculto.
        if ($this->proveedorForzado !== null) {
            $this->proveedor_id = (string) $this->proveedorForzado;
        }

        $datos = $this->validate($this->rules());

        // El cuadre se comprueba también aquí: la vista ya deshabilita el
        // botón, pero un componente Livewire es un endpoint invocable.
        if (! $this->cuadra) {
            $this->addError('total_pagado', 'El detalle por producto debe sumar exactamente el total pagado.');

            return;
        }

        $productos = Producto::whereIn('id', collect($datos['lineas'])->pluck('producto_id'))
            ->get()
            ->keyBy('id');

        try {
            $compra = DB::transaction(function () use ($datos, $productos): Compra {
                $compra = app(GeneradorCodigoCompra::class)->crearCon([
                    'proveedor_id' => (int) $datos['proveedor_id'],
                    'numero_factura' => $datos['numero_factura'] !== '' ? $datos['numero_factura'] : null,
                    'fecha_compra' => $datos['fecha_compra'],
                    'notas' => $datos['notas'] !== '' ? $datos['notas'] : null,
                    'user_id' => auth()->id(),
                    // El detalle cuadra con lo pagado, así que subtotal y total
                    // coinciden. Los gastos aparte quedan en cero: no hay
                    // ningún importe que repartir por fuera de los productos.
                    'subtotal' => ProrrateoDeGastos::aDecimal($this->pagadoEnCentavos),
                    'total' => ProrrateoDeGastos::aDecimal($this->pagadoEnCentavos),
                    'descuento' => '0.00',
                    'impuesto' => '0.00',
                    'flete' => '0.00',
                    'otros_gastos' => '0.00',
                    // Nace ya recepcionada: las unidades se crean en el acto.
                    'estado' => 'borrador',
                ]);

                foreach ($datos['lineas'] as $linea) {
                    $producto = $productos[$linea['producto_id']];
                    $cantidad = (int) $linea['cantidad'];
                    $pagado = ProrrateoDeGastos::aCentavos($linea['costo_total']);

                    CompraDetalle::create([
                        'compra_id' => $compra->id,
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        // Promedio, solo de referencia: el reparto exacto al
                        // centavo lo hace RecepcionDeCompra sobre cada unidad.
                        'costo_unitario' => ProrrateoDeGastos::aDecimal(intdiv($pagado, $cantidad)),
                        'subtotal' => ProrrateoDeGastos::aDecimal($pagado),
                        'precio_venta' => $this->precioDeVentaDe($producto),
                    ]);
                }

                // Genera las unidades y deja la compra en 'recepcionada'.
                app(RecepcionDeCompra::class)->recepcionar($compra->fresh());

                return $compra->fresh();
            });
        } catch (Throwable $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se pudo registrar la compra: '.$e->getMessage());

            return;
        }

        $generadas = $compra->unidades()->count();

        $this->limpiarCabecera();
        $this->dispatch('cerrar-modal-compra');
        $this->dispatch('toast', tipo: 'success', mensaje: "Compra {$compra->codigo} registrada: se generaron {$generadas} unidades en el inventario.");

        // Se abre su detalle: es donde se registran los seriales.
        $this->abrirDetalle($compra->id);
    }

    // =======================================================================
    // Detalle de la compra
    // =======================================================================

    public function abrirDetalle(int $compraId): void
    {
        $this->autorizar('compras.ver');

        $this->detalleCompraId = $compraId;
        unset($this->unidadesDeLaCompra);
    }

    public function cerrarDetalle(): void
    {
        $this->detalleCompraId = null;
        $this->seriales = [];
    }

    public function quitarFiltroProveedor(): void
    {
        $this->proveedorForzado = null;
        $this->proveedor_id = '';
        $this->resetPage();
    }

    // =======================================================================
    // Anulación
    // =======================================================================

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('compras.eliminar');

        $compra = Compra::findOrFail($id);

        $this->eliminarId = $compra->id;
        $this->eliminarCodigo = $compra->codigo;

        $this->dispatch('abrir-modal-eliminar-compra');
    }

    public function eliminar(): void
    {
        $this->autorizar('compras.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $compra = Compra::findOrFail($this->eliminarId);

        // Una compra recepcionada no se borra: sus unidades ya están en el
        // almacén o vendidas, y quedarían sin origen.
        if (! $compra->es_borrador) {
            $this->dispatch('cerrar-modal-eliminar-compra');
            $this->dispatch('toast', tipo: 'error', mensaje: 'Una compra recepcionada no se puede eliminar.');

            return;
        }

        $compra->delete();

        if ($this->detalleCompraId === $compra->id) {
            $this->cerrarDetalle();
        }

        $this->reset(['eliminarId', 'eliminarCodigo']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-compra');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Compra eliminada correctamente.');
    }

    // =======================================================================

    public function ordenar(string $campo): void
    {
        $this->direccionOrden = $this->ordenarPor === $campo && $this->direccionOrden === 'asc'
            ? 'desc'
            : 'asc';

        $this->ordenarPor = $campo;
        $this->resetPage();
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarCabecera(): void
    {
        $forzado = $this->proveedorForzado;

        $this->reset([
            ...self::CAMPOS_CABECERA,
            'compraId', 'buscarProducto', 'categoriaLinea', 'marcaLinea',
        ]);

        // Si se llegó con ?proveedor=, el contexto forzado debe sobrevivir
        // a cada apertura del modal y al registro exitoso.
        if ($forzado !== null) {
            $this->proveedorForzado = $forzado;
            $this->proveedor_id = (string) $forzado;
        } else {
            $this->reset(['proveedorForzado']);
        }

        $this->resetValidation();
    }

    public function render(): View
    {
        $compras = Compra::query()
            ->with(['proveedor', 'user'])
            ->withCount(['detalles', 'unidades'])
            ->buscar($this->buscar)
            ->when($this->proveedorForzado !== null, fn ($q) => $q->where('proveedor_id', $this->proveedorForzado))
            ->when($this->filtroEstado !== 'todos', fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id', 'desc')
            ->paginate(10);

        $proveedorContexto = null;

        if ($this->proveedorForzado !== null) {
            $proveedorContexto = Proveedor::find($this->proveedorForzado);
        }

        return view('livewire.compras.index', [
            'compras' => $compras,
            'totalCompras' => Compra::count(),
            'enBorrador' => Compra::where('estado', 'borrador')->count(),
            'invertidoMes' => Compra::where('estado', 'recepcionada')
                ->whereYear('fecha_compra', now()->year)
                ->whereMonth('fecha_compra', now()->month)
                ->sum('total'),
            'proveedorContexto' => $proveedorContexto,
        ]);
    }
}
