<?php

namespace App\Livewire\Unidades;

use App\Models\Unidad;
use App\Models\Producto;
use App\Support\GeneradorCodigoUnidad;
use App\Support\Kardex;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    public ?int $productoFiltro = null;

    public string $estadoFiltro = '';

    public string $ordenarPor = 'codigo_interno';

    public string $direccionOrden = 'asc';

    /** Id de la unidad en edición; null significa "registro nuevo". */
    public ?int $itemId = null;

    /**
     * Unidades marcadas para imprimir sus etiquetas.
     *
     * Se guardan como cadenas porque es lo que devuelve el binding de los
     * checkbox; al construir la URL se normalizan a enteros.
     *
     * @var array<int, string>
     */
    public array $seleccionadas = [];

    // ---- Campos del formulario -------------------------------------------

    public ?int $productoId = null;

    public string $serial = '';

    /** Costo y precio se manejan como cadenas para conservar el formato. */
    public string $costo = '';

    public string $precio = '';

    public string $estado = 'en_stock';

    public string $ubicacion = '';

    public string $fechaIngreso = '';

    public string $notas = '';

    /** Código interno real de la unidad que se está editando (solo lectura). */
    public string $codigoActual = '';

    private const CAMPOS = [
        'productoId',
        'serial',
        'costo',
        'precio',
        'estado',
        'ubicacion',
        'fechaIngreso',
        'notas',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        if ($this->itemId !== null) {
            // Edición: se permite ajustar datos de la unidad (garantía vive en producto).
            return [
                'serial' => [
                    'nullable', 'string', 'max:100',
                    Rule::unique('unidades', 'serial')->ignore($this->itemId)->whereNull('deleted_at'),
                ],
                'costo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'precio' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'estado' => ['nullable', Rule::in(array_keys(Unidad::ESTADOS))],
                'ubicacion' => ['nullable', 'string', 'max:120'],
                'fechaIngreso' => ['nullable', 'date'],
                'notas' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return [
            'productoId' => ['required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'serial' => [
                'nullable', 'string', 'max:100',
                Rule::unique('unidades', 'serial')->ignore($this->itemId)->whereNull('deleted_at'),
            ],
            'costo' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'precio' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'estado' => ['required', Rule::in(array_keys(Unidad::ESTADOS))],
            'ubicacion' => ['nullable', 'string', 'max:120'],
            'fechaIngreso' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'productoId.required' => 'Elige un producto.',
            'productoId.exists' => 'El producto elegido ya no existe.',
            'serial.unique' => 'Ya existe una unidad con este serial.',
            'costo.required' => 'El costo es obligatorio.',
            'costo.numeric' => 'El costo debe ser un número.',
            'precio.required' => 'El precio de venta es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'estado.required' => 'Elige un estado.',
            'estado.in' => 'El estado elegido no es válido.',
            'fechaIngreso.required' => 'La fecha de ingreso es obligatoria.',
            'fechaIngreso.date' => 'La fecha de ingreso no es válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'productoId' => 'producto',
            'serial' => 'serial',
            'costo' => 'costo',
            'precio' => 'precio de venta',
            'estado' => 'estado',
            'ubicacion' => 'ubicación',
            'fechaIngreso' => 'fecha de ingreso',
            'notas' => 'notas',
        ];
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, self::CAMPOS, true)) {
            $this->validateOnly($campo);
        }
    }

    /**
     * Al elegir un producto en un registro nuevo se sugiere el precio de
     * lista del producto como precio de salida de la unidad.
     */
    public function updatedProductoId(): void
    {
        if ($this->itemId !== null || $this->productoId === null || $this->precio !== '') {
            return;
        }

        $producto = Producto::find($this->productoId);

        if ($producto !== null && (float) $producto->precio_venta > 0) {
            $this->precio = number_format((float) $producto->precio_venta, 2, '.', '');
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedProductoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    /**
     * Si se llega desde el listado de productos, el producto viaja en la
     * sesión (nunca en la URL) y el listado se abre ya filtrado. Se consume
     * con pull(): al volver sin pasar por un producto se ve todo el inventario.
     */
    public function mount(): void
    {
        $this->estadoFiltro = 'en_stock';

        $producto = (int) session()->pull('producto_activo', 0);

        if ($producto > 0) {
            $this->productoFiltro = $producto;
        }
    }

    #[Computed]
    public function formularioValido(): bool
    {
        $campos = $this->itemId !== null
            ? ['serial', 'ubicacion', 'notas']
            : self::CAMPOS;

        return Validator::make(
            $this->only($campos),
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    /**
     * Previsualización del código interno que recibirá una unidad nueva.
     * En edición se muestra el código ya asignado (codigoActual).
     */
    #[Computed]
    public function codigoPreview(): string
    {
        if ($this->productoId === null) {
            return '';
        }

        $producto = Producto::find($this->productoId);

        if ($producto === null) {
            return '';
        }

        return app(GeneradorCodigoUnidad::class)->siguiente($producto);
    }

    /**
     * URL de la hoja de etiquetas de las unidades marcadas.
     *
     * Los ids viajan por la URL para poder abrir la hoja en una pestaña nueva
     * con un enlace normal, sin postear un formulario.
     */
    #[Computed]
    public function urlEtiquetas(): ?string
    {
        $ids = collect($this->seleccionadas)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        return $ids->isEmpty()
            ? null
            : route('etiquetas.unidades', ['ids' => $ids->implode(',')]);
    }

    /** Marca o desmarca todas las unidades de la página actual. */
    public function alternarPagina(array $idsDeLaPagina): void
    {
        $todasMarcadas = empty(array_diff($idsDeLaPagina, $this->seleccionadas));

        $this->seleccionadas = $todasMarcadas
            ? array_values(array_diff($this->seleccionadas, $idsDeLaPagina))
            : array_values(array_unique([...$this->seleccionadas, ...$idsDeLaPagina]));
    }

    public function limpiarSeleccion(): void
    {
        $this->seleccionadas = [];
    }

    public function ordenar(string $campo): void
    {
        $this->direccionOrden = $this->ordenarPor === $campo && $this->direccionOrden === 'asc'
            ? 'desc'
            : 'asc';

        $this->ordenarPor = $campo;
        $this->resetPage();
    }

    // ---- Alta y edición ---------------------------------------------------

    public function abrirCrear(): void
    {
        $this->autorizar('unidades.crear');

        $this->limpiarFormulario();

        // Toda unidad nueva entra al almacén disponible: el estado no se
        // pregunta al registrarla, se fija. Los demás estados (vendido,
        // dañado, en garantía…) son transiciones posteriores y se cambian
        // editando la unidad.
        $this->estado = 'en_stock';
        $this->fechaIngreso = now()->format('Y-m-d');

        // Si se llegó desde un producto, la unidad nace en él: el modal no
        // muestra selector, solo informa de qué producto se trata.
        if ($this->productoFiltro !== null) {
            $this->productoId = $this->productoFiltro;
            $this->updatedProductoId();
        }

        $this->dispatch('abrir-modal-item');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('unidades.editar');

        $unidad = Unidad::findOrFail($id);

        $this->itemId = $unidad->id;
        $this->productoId = $unidad->producto_id;
        $this->serial = (string) $unidad->serial;
        $this->ubicacion = (string) $unidad->ubicacion;
        $this->notas = (string) $unidad->notas;
        $this->codigoActual = $unidad->codigo_interno;

        $this->resetValidation();
        $this->dispatch('abrir-modal-item');
    }

    public function guardar(): void
    {
        $this->autorizar($this->itemId !== null ? 'unidades.editar' : 'unidades.crear');

        // Una unidad nueva siempre entra en stock, venga de donde venga la
        // propiedad: el formulario no la deja elegir y aquí se vuelve a fijar,
        // porque un componente Livewire es un endpoint invocable.
        if ($this->itemId === null) {
            $this->estado = 'en_stock';
        }

        $validados = $this->validate();

        if ($this->itemId !== null) {
            $mensaje = DB::transaction(function () use ($validados): string {
                $unidad = Unidad::findOrFail($this->itemId);
                $payload = [
                    'serial' => $validados['serial'] === '' ? null : $validados['serial'],
                    'ubicacion' => $validados['ubicacion'] === '' ? null : $validados['ubicacion'],
                    'notas' => $validados['notas'] === '' ? null : $validados['notas'],
                ];
                // Compatibilidad con tests y con edición directa vía Livewire:
                // se permiten ajustes de costo/precio/estado/fecha si vienen validados.
                if (array_key_exists('costo', $validados) && $validados['costo'] !== '' && $validados['costo'] !== null) {
                    $payload['costo_unitario'] = (float) $validados['costo'];
                }
                if (array_key_exists('precio', $validados) && $validados['precio'] !== '' && $validados['precio'] !== null) {
                    $payload['precio_venta'] = (float) $validados['precio'];
                }
                if (array_key_exists('estado', $validados) && $validados['estado'] !== '' && $validados['estado'] !== null) {
                    $payload['estado'] = $validados['estado'];
                }
                if (array_key_exists('fechaIngreso', $validados) && $validados['fechaIngreso'] !== '' && $validados['fechaIngreso'] !== null) {
                    $payload['ingresado_en'] = $validados['fechaIngreso'].' 00:00:00';
                }
                $unidad->update($payload);

                return 'Unidad actualizada correctamente.';
            });
        } else {
            $datos = [
                'producto_id' => $this->productoId,
                'serial' => $validados['serial'] === '' ? null : $validados['serial'],
                'costo_unitario' => (float) $validados['costo'],
                'precio_venta' => (float) $validados['precio'],
                'estado' => $validados['estado'],
                'ubicacion' => $validados['ubicacion'] === '' ? null : $validados['ubicacion'],
                'ingresado_en' => $validados['fechaIngreso'].' 00:00:00',
                'vendido_en' => $validados['estado'] === 'vendido' ? now() : null,
                'notas' => $validados['notas'] === '' ? null : $validados['notas'],
            ];

            // Alta y edición dejan rastro en el kardex dentro de la misma
            // transacción: inventario sin historia no se puede auditar.
            $mensaje = DB::transaction(function () use ($datos): string {
                $unidad = app(GeneradorCodigoUnidad::class)->crearCon($datos);

                // Alta manual: es la regularización del stock que ya existía,
                // sin compra detrás. Por eso el movimiento no lleva origen.
                app(Kardex::class)->entrada($unidad, notas: 'Alta manual de regularización');

                return 'Unidad registrada correctamente.';
            });
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-item');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // ---- Eliminación ------------------------------------------------------
    public ?int $eliminarId = null;

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('unidades.eliminar');
        $unidad = Unidad::findOrFail($id);
        if ($unidad->estado === 'vendido') {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se puede eliminar una unidad vendida.');
            return;
        }
        $this->eliminarId = $unidad->id;
        $this->dispatch('abrir-modal-eliminar-item');
    }

    public function eliminar(): void
    {
        $this->autorizar('unidades.eliminar');
        if ($this->eliminarId === null) {
            return;
        }
        $unidad = Unidad::findOrFail($this->eliminarId);
        if ($unidad->estado === 'vendido') {
            $this->dispatch('cerrar-modal-eliminar-item');
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se puede eliminar una unidad vendida.');
            return;
        }
        $unidad->delete();
        $this->reset(['eliminarId']);
        $this->resetPage();
        $this->dispatch('cerrar-modal-eliminar-item');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Unidad eliminada correctamente.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'itemId', 'codigoActual']);
        $this->resetValidation();
    }

    // ---- Selectores -------------------------------------------------------

    /**
     * @return Collection<int, Producto>
     */
    private function opcionesProductos(): Collection
    {
        return Producto::query()->orderBy('nombre')->get();
    }

    public function render(): View
    {
        $termino = trim($this->buscar);

        // Al entrar desde un producto se muestra su ficha completa encima del
        // listado: categoría con su ruta, marca, precios, garantía y
        // especificaciones. Evita saltar al módulo de productos para
        // consultar de qué se están registrando unidades.
        $producto = $this->productoFiltro !== null
            ? Producto::with(['categoria.padre', 'marca'])->find($this->productoFiltro)
            : null;

        $unidades = Unidad::query()
            ->with('producto')
            ->with('compra.proveedor')
            ->with('ventaDetalle.venta')
            ->when($this->productoFiltro, fn ($q) => $q->where('producto_id', $this->productoFiltro))
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('estado', $this->estadoFiltro))
            ->when($termino !== '', fn ($q) => $q->where(function ($q2) use ($termino) {
                $q2->where('codigo_interno', 'like', "%{$termino}%")
                    ->orWhere('serial', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('sku', 'like', "%{$termino}%"));
            }))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            ->orderBy('id')
            ->paginate(10);

        // Los indicadores acompañan al contexto: dentro de un producto son los
        // suyos, si no los de todo el inventario. De otro modo el encabezado
        // contradiría a la tabla que tiene debajo.
        $totales = Unidad::query()
            ->when($producto !== null, fn ($q) => $q->where('producto_id', $producto->id))
            ->selectRaw('count(*) as total, sum(case when estado = "en_stock" then 1 else 0 end) as en_stock, sum(case when estado = "vendido" then 1 else 0 end) as vendidos, sum(case when estado = "en_stock" then precio_venta else 0 end) as valor')
            ->first();

        // Desglose por estado del producto abierto, para la ficha.
        $porEstado = $producto === null
            ? collect()
            : Unidad::query()
                ->where('producto_id', $producto->id)
                ->selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

        // Estados que al menos tienen una unidad en inventario (para tabs).
        $estadosConUnidades = Unidad::query()
            ->when($producto !== null, fn ($q) => $q->where('producto_id', $producto->id))
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('livewire.unidades.index', [
            'unidades' => $unidades,
            'totalUnidades' => (int) $totales->total,
            'enStock' => (int) $totales->en_stock,
            'vendidos' => (int) $totales->vendidos,
            'valorInventario' => (float) $totales->valor,
            'productos' => $this->opcionesProductos(),
            'estados' => Unidad::ESTADOS,
            'producto' => $producto,
            'unidadesPorEstado' => $porEstado,
            'estadosConUnidades' => $estadosConUnidades,
        ]);
    }
}
