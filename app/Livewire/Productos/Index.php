<?php

namespace App\Livewire\Productos;

use App\Models\Marca;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    public ?int $categoriaFiltro = null;

    public ?int $marcaFiltro = null;

    public string $ordenarPor = 'nombre';

    public string $direccionOrden = 'asc';

    /** Id del producto en edición; null significa "registro nuevo". */
    public ?int $productoId = null;

    /** Producto pendiente de eliminar. */
    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    // ---- Campos del formulario -------------------------------------------

    public string $nombre = '';

    public string $slug = '';

    public string $sku = '';

    public ?int $categoriaId = null;

    public ?int $marcaId = null;

    public string $modelo = '';

    public string $descripcion = '';

    /**
     * Especificaciones como filas clave/valor: [['clave' => 'Pantalla', 'valor' => '55"'], ...].
     * Se editan con un campo por dato en vez de un textarea de líneas, así el
     * usuario agrega o quita especificaciones sin depender del formato del texto.
     * En la base siguen guardándose como objeto JSON (ver parsearEspecificaciones).
     *
     * @var array<int, array{clave: string, valor: string}>
     */
    public array $especificaciones = [];

    /** Imagen subida en este formulario. */
    public $imagen = null;

    public string $imagenActual = '';

    public bool $quitarImagen = false;

    /** El precio se maneja como cadena para conservar el formato en el input. */
    public string $precio = '';

    /**
     * Rebaja máxima en Bs que el mostrador puede aplicar a este producto.
     * 0 significa "se cobra el precio de lista": el POS no deja bajarlo.
     */
    public string $descuentoMaximo = '0';

    public int $minStock = 0;

    public int $mesesGarantia = 12;

    public bool $isActive = true;

    public bool $slugManual = false;

    // ---- Formulario rápido de marca ----------------------------------------

    /** Nombre de la nueva marca a crear desde el formulario de producto. */
    public string $nuevaMarcaNombre = '';

    /** Slug de la nueva marca. */
    public string $nuevaMarcaSlug = '';

    /** Si el usuario editó el slug de la marca a mano. */
    public bool $slugManualMarca = false;

    /** Muestra u oculta el formulario inline de nueva marca. */
    public bool $mostrarFormularioMarca = false;

    private const CAMPOS = [
        'nombre',
        'slug',
        'sku',
        'categoriaId',
        'marcaId',
        'modelo',
        'descripcion',
        'especificaciones',
        'precio',
        'descuentoMaximo',
        'minStock',
        'mesesGarantia',
        'isActive',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'slug' => [
                'required', 'string', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('productos', 'slug')->ignore($this->productoId)->whereNull('deleted_at'),
            ],
            'sku' => [
                'required', 'string', 'regex:/^[A-Za-z0-9\-]{3,40}$/',
                Rule::unique('productos', 'sku')->ignore($this->productoId)->whereNull('deleted_at'),
            ],
            'categoriaId' => ['required', 'integer', Rule::exists('categorias', 'id')->whereNull('deleted_at')],
            'marcaId' => ['nullable', 'integer', Rule::exists('marcas', 'id')],
            'modelo' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'especificaciones' => ['array', 'max:40'],
            'especificaciones.*.clave' => ['nullable', 'string', 'max:60'],
            'especificaciones.*.valor' => ['nullable', 'string', 'max:200'],
            'precio' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // Nunca por encima del precio: un descuento mayor dejaría vender
            // el aparato gratis o con importe negativo.
            'descuentoMaximo' => ['required', 'numeric', 'min:0', 'lte:precio'],
            'minStock' => ['integer', 'min:0', 'max:999999'],
            'mesesGarantia' => ['integer', 'min:0', 'max:240'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'slug.required' => 'El slug es obligatorio.',
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe un producto con este slug.',
            'sku.required' => 'El SKU es obligatorio.',
            'sku.regex' => 'El SKU solo puede contener letras, números y guiones (3 a 40 caracteres).',
            'sku.unique' => 'Ya existe un producto con este SKU.',
            'categoriaId.required' => 'Elige una categoría.',
            'categoriaId.exists' => 'La categoría elegida ya no existe.',
            'marcaId.exists' => 'La marca elegida ya no existe.',
            'precio.required' => 'El precio de venta es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'descuentoMaximo.lte' => 'El descuento máximo no puede superar al precio de venta.',
            'descuentoMaximo.numeric' => 'El descuento máximo debe ser un número.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'imagen.max' => 'La imagen no puede pesar más de 3 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre del producto',
            'slug' => 'slug',
            'sku' => 'SKU',
            'categoriaId' => 'categoría',
            'marcaId' => 'marca',
            'modelo' => 'modelo',
            'descripcion' => 'descripción',
            'especificaciones' => 'especificaciones',
            'especificaciones.*.clave' => 'característica',
            'especificaciones.*.valor' => 'valor',
            'precio' => 'precio de venta',
            'descuentoMaximo' => 'descuento máximo',
            'minStock' => 'stock mínimo',
            'mesesGarantia' => 'meses de garantía',
            'imagen' => 'imagen',
            'isActive' => 'estado',
        ];
    }

    public function updatedNombre(): void
    {
        if (! $this->slugManual) {
            $this->slug = Str::slug($this->nombre);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManual = true;
    }

    public function updatedImagen(): void
    {
        $this->resetValidation('imagen');
    }

    public function updated(string $campo): void
    {
        // Las filas de especificaciones llegan como "especificaciones.0.clave",
        // que no está en CAMPOS pero sí tiene regla con comodín.
        if (in_array($campo, self::CAMPOS, true) || str_starts_with($campo, 'especificaciones.')) {
            $this->validateOnly($campo);
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedCategoriaFiltro(): void
    {
        $this->resetPage();

        // La marca elegida puede no existir en la categoría a la que se acaba
        // de entrar. Dejarla puesta daría un listado vacío sin que se vea por
        // qué, porque su opción ya no aparece en el selector: se suelta.
        if ($this->marcaFiltro !== null && ! $this->marcaTieneProductosEnRama()) {
            $this->marcaFiltro = null;
        }
    }

    /**
     * ¿La marca filtrada tiene algún producto dentro de la categoría activa
     * (o de su descendencia)?
     */
    private function marcaTieneProductosEnRama(): bool
    {
        $idsRama = $this->idsRama();

        if ($idsRama === []) {
            return true;
        }

        return Producto::query()
            ->where('marca_id', $this->marcaFiltro)
            ->whereIn('categoria_id', $idsRama)
            ->exists();
    }

    /**
     * Ids de la categoría activa y de toda su descendencia. Vacío cuando no
     * hay categoría: significa "todo el catálogo", no "ninguna categoría".
     *
     * @return array<int, int>
     */
    private function idsRama(?Categoria $categoria = null): array
    {
        $categoria ??= $this->categoriaFiltro !== null
            ? Categoria::find($this->categoriaFiltro)
            : null;

        return $categoria !== null
            ? [$categoria->id, ...$categoria->descendientesIds()]
            : [];
    }

    public function updatedMarcaFiltro(): void
    {
        $this->resetPage();
    }

    /**
     * Si se llega desde el listado de categorías, la categoría viaja en la
     * sesión (nunca en la URL) y el listado se abre ya filtrado. Se consume
     * con pull(): si el usuario navega después a /productos sin pasar por
     * una categoría, vuelve a verse todo el catálogo.
     */
    public function mount(): void
    {
        $categoria = (int) session()->pull('categoria_activa', 0);

        if ($categoria > 0) {
            $this->categoriaFiltro = $categoria;
        }
    }

    #[Computed]
    public function formularioValido(): bool
    {
        return Validator::make(
            $this->only(self::CAMPOS),
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
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
        $this->autorizar('productos.crear');

        $this->limpiarFormulario();

        // Si se llegó desde una categoría, el producto nace en ella: el modal
        // no muestra selector, solo informa en cuál se va a registrar.
        if ($this->categoriaFiltro !== null) {
            $this->categoriaId = $this->categoriaFiltro;
        }

        // Una fila en blanco para que la sección de especificaciones no
        // aparezca vacía y se entienda de un vistazo cómo se llena.
        $this->especificaciones = [['clave' => '', 'valor' => '']];

        $this->dispatch('abrir-modal-producto');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('productos.editar');

        $producto = Producto::findOrFail($id);

        $this->productoId = $producto->id;
        $this->nombre = $producto->nombre;
        $this->slug = $producto->slug;
        $this->sku = $producto->sku;
        $this->categoriaId = $producto->categoria_id;
        $this->marcaId = $producto->marca_id;
        $this->modelo = (string) $producto->modelo;
        $this->descripcion = (string) $producto->descripcion;
        $this->especificaciones = $this->formatearEspecificaciones((array) $producto->especificaciones);
        $this->imagenActual = (string) $producto->imagen;
        $this->imagen = null;
        $this->quitarImagen = false;
        $this->precio = number_format((float) $producto->precio_venta, 2, '.', '');
        $this->descuentoMaximo = number_format((float) $producto->descuento_maximo, 2, '.', '');
        $this->minStock = $producto->stock_minimo;
        $this->mesesGarantia = $producto->meses_garantia;
        $this->isActive = $producto->activo;
        $this->slugManual = true;

        $this->resetValidation();
        $this->dispatch('abrir-modal-producto');
    }

    public function guardar(): void
    {
        $this->autorizar($this->productoId !== null ? 'productos.editar' : 'productos.crear');

        $validados = $this->validate();

        $datos = [
            'nombre' => $validados['nombre'],
            'slug' => $validados['slug'],
            'sku' => strtoupper($validados['sku']),
            'categoria_id' => $this->categoriaId,
            'marca_id' => $this->marcaId ?: null,
            'modelo' => $validados['modelo'] === '' ? null : $validados['modelo'],
            'descripcion' => $validados['descripcion'] === '' ? null : $validados['descripcion'],
            'especificaciones' => $this->parsearEspecificaciones($validados['especificaciones'] ?? []),
            'precio_venta' => (float) $validados['precio'],
            'descuento_maximo' => (float) $validados['descuentoMaximo'],
            'stock_minimo' => $validados['minStock'],
            'meses_garantia' => $validados['mesesGarantia'],
            'activo' => (bool) $this->isActive,
        ];

        if (! $this->slugManual) {
            $datos['slug'] = Str::slug($this->nombre);
        }

        $rutaImagen = $this->imagenActual;

        if ($this->imagen) {
            $rutaImagen = $this->imagen->store('productos', 'public');
        } elseif ($this->quitarImagen && $this->imagenActual !== '') {
            Storage::disk('public')->delete($this->imagenActual);
            $rutaImagen = null;
        }

        $datos['imagen'] = $rutaImagen === '' ? null : $rutaImagen;

        if ($this->productoId !== null) {
            if ($this->imagen && $this->imagenActual !== '') {
                Storage::disk('public')->delete($this->imagenActual);
            }

            Producto::findOrFail($this->productoId)->update($datos);
            $mensaje = 'Producto actualizado correctamente.';
        } else {
            Producto::create($datos);
            $mensaje = 'Producto creado correctamente.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-producto');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // ---- Navegación -------------------------------------------------------

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

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('productos.eliminar');

        $producto = Producto::findOrFail($id);

        $this->eliminarId = $producto->id;
        $this->eliminarNombre = $producto->nombre;

        $this->dispatch('abrir-modal-eliminar-producto');
    }

    public function eliminar(): void
    {
        $this->autorizar('productos.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $producto = Producto::findOrFail($this->eliminarId);

        if ($producto->imagen) {
            Storage::disk('public')->delete($producto->imagen);
        }

        $producto->delete();

        $this->reset(['eliminarId', 'eliminarNombre']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-producto');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Producto eliminado correctamente.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'productoId', 'imagen', 'imagenActual', 'quitarImagen']);
        $this->slugManual = false;
        $this->cerrarFormularioMarca();
        $this->resetValidation();
    }

    // ---- Formulario rápido de marca ----------------------------------------

    public function abrirFormularioMarca(): void
    {
        $this->mostrarFormularioMarca = true;
        $this->nuevaMarcaNombre = '';
        $this->nuevaMarcaSlug = '';
        $this->slugManualMarca = false;
        $this->resetValidation('nuevaMarcaNombre');
        $this->resetValidation('nuevaMarcaSlug');
    }

    public function cerrarFormularioMarca(): void
    {
        $this->mostrarFormularioMarca = false;
        $this->nuevaMarcaNombre = '';
        $this->nuevaMarcaSlug = '';
        $this->slugManualMarca = false;
        $this->resetValidation('nuevaMarcaNombre');
        $this->resetValidation('nuevaMarcaSlug');
    }

    public function updatedNuevaMarcaNombre(): void
    {
        if (! $this->slugManualMarca) {
            $this->nuevaMarcaSlug = Str::slug($this->nuevaMarcaNombre);
        }
    }

    public function updatedNuevaMarcaSlug(): void
    {
        $this->slugManualMarca = true;
    }

    #[Computed]
    public function formularioMarcaValido(): bool
    {
        return Validator::make(
            ['nuevaMarcaNombre' => $this->nuevaMarcaNombre, 'nuevaMarcaSlug' => $this->nuevaMarcaSlug],
            [
                'nuevaMarcaNombre' => ['required', 'string', 'min:2', 'max:100', Rule::unique('marcas', 'nombre')],
                'nuevaMarcaSlug' => [
                    'required', 'string', 'regex:/^[a-z0-9\-]+$/',
                    Rule::unique('marcas', 'slug'),
                ],
            ],
            [
                'nuevaMarcaNombre.required' => 'El nombre de la marca es obligatorio.',
                'nuevaMarcaNombre.min' => 'El nombre debe tener al menos 2 caracteres.',
                'nuevaMarcaNombre.unique' => 'Ya existe una marca con este nombre.',
                'nuevaMarcaSlug.required' => 'El slug es obligatorio.',
                'nuevaMarcaSlug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
                'nuevaMarcaSlug.unique' => 'Ya existe una marca con este slug.',
            ],
            [
                'nuevaMarcaNombre' => 'nombre',
                'nuevaMarcaSlug' => 'slug',
            ]
        )->passes();
    }

    public function guardarMarcaRapida(): void
    {
        $this->autorizar('marcas.crear');

        $validados = Validator::make(
            ['nuevaMarcaNombre' => $this->nuevaMarcaNombre, 'nuevaMarcaSlug' => $this->nuevaMarcaSlug],
            [
                'nuevaMarcaNombre' => ['required', 'string', 'min:2', 'max:100', Rule::unique('marcas', 'nombre')],
                'nuevaMarcaSlug' => [
                    'required', 'string', 'regex:/^[a-z0-9\-]+$/',
                    Rule::unique('marcas', 'slug'),
                ],
            ],
            [
                'nuevaMarcaNombre.required' => 'El nombre de la marca es obligatorio.',
                'nuevaMarcaNombre.min' => 'El nombre debe tener al menos 2 caracteres.',
                'nuevaMarcaNombre.unique' => 'Ya existe una marca con este nombre.',
                'nuevaMarcaSlug.required' => 'El slug es obligatorio.',
                'nuevaMarcaSlug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
                'nuevaMarcaSlug.unique' => 'Ya existe una marca con este slug.',
            ],
            [
                'nuevaMarcaNombre' => 'nombre',
                'nuevaMarcaSlug' => 'slug',
            ]
        )->validate();

        $slug = $this->slugManualMarca
            ? $this->nuevaMarcaSlug
            : Str::slug($this->nuevaMarcaNombre);

        $marca = Marca::create([
            'nombre' => $validados['nuevaMarcaNombre'],
            'slug' => $slug,
            'activa' => true,
        ]);

        // Seleccionar la marca recién creada en el formulario de producto.
        $this->marcaId = $marca->id;

        $this->cerrarFormularioMarca();
        $this->dispatch('toast', tipo: 'success', mensaje: 'Marca "'.$marca->nombre.'" creada y seleccionada.');
    }

    // ---- Especificaciones -------------------------------------------------

    /**
     * Añade una fila vacía al final del repetidor. El formulario no se envía:
     * la fila se agrega en el mismo modal y se rellena antes de guardar.
     */
    public function agregarEspecificacion(): void
    {
        if (count($this->especificaciones) >= 40) {
            $this->dispatch('toast', tipo: 'warning', mensaje: 'Máximo 40 especificaciones por producto.');

            return;
        }

        $this->especificaciones[] = ['clave' => '', 'valor' => ''];
    }

    public function quitarEspecificacion(int $indice): void
    {
        unset($this->especificaciones[$indice]);

        // Reindexar: si quedan huecos, Livewire deja de casar las filas con sus
        // inputs y el usuario ve valores en la fila equivocada.
        $this->especificaciones = array_values($this->especificaciones);

        $this->resetValidation('especificaciones');
    }

    /**
     * Convierte las filas del repetidor en el objeto JSON que guarda la tabla.
     * Una fila con característica pero sin valor se guarda como bandera
     * (clave => true); las filas sin característica se descartan.
     *
     * @param  array<int, array{clave?: string, valor?: string}>  $filas
     * @return array<string, mixed>|null
     */
    private function parsearEspecificaciones(array $filas): ?array
    {
        $especificaciones = [];

        foreach ($filas as $fila) {
            $clave = trim((string) ($fila['clave'] ?? ''));
            $valor = trim((string) ($fila['valor'] ?? ''));

            if ($clave === '') {
                continue;
            }

            $especificaciones[$clave] = $valor === '' ? true : $valor;
        }

        return $especificaciones === [] ? null : $especificaciones;
    }

    /**
     * Camino inverso: del JSON guardado a las filas que edita el formulario.
     *
     * @param  array<string, mixed>  $especificaciones
     * @return array<int, array{clave: string, valor: string}>
     */
    private function formatearEspecificaciones(array $especificaciones): array
    {
        $filas = [];

        foreach ($especificaciones as $clave => $valor) {
            $filas[] = [
                'clave' => (string) $clave,
                'valor' => $valor === true ? '' : (string) $valor,
            ];
        }

        return $filas;
    }

    // ---- Selectores -------------------------------------------------------

    /**
     * Categorías en forma de árbol plano para el selector del formulario y
     * para el filtro del listado.
     *
     * @return array<int, array{id: int, nombre: string, nivel: int}>
     */
    private function opcionesCategorias(): array
    {
        $categorias = Categoria::query()->ordenadas()->get();
        $porPadre = $categorias->groupBy(fn (Categoria $c): int => $c->padre_id ?? 0);

        $opciones = [];

        $recorrer = function (int $padreId, int $nivel) use ($porPadre, &$recorrer, &$opciones): void {
            foreach ($porPadre->get($padreId, collect()) as $categoria) {
                $opciones[] = [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'nivel' => $nivel,
                ];

                $recorrer($categoria->id, $nivel + 1);
            }
        };

        $recorrer(0, 0);

        return $opciones;
    }

    /**
     * Marcas del filtro del listado. Dentro de una categoría se acotan a las
     * que realmente tienen productos en esa rama (la categoría y su
     * descendencia): ofrecer marcas que no darían ningún resultado convierte
     * el filtro en una lista de callejones sin salida.
     *
     * @param  array<int, int>  $idsRama  Vacío = todo el catálogo.
     * @return Collection<int, Marca>
     */
    private function marcasParaFiltro(array $idsRama): Collection
    {
        return Marca::query()
            ->activas()
            ->when($idsRama !== [], fn ($q) => $q->whereHas(
                'productos',
                fn ($p) => $p->whereIn('categoria_id', $idsRama)
            ))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Marcas del formulario. Aquí NO se acotan por categoría: el primer
     * producto de una marca en una categoría nueva tiene que poder elegirla.
     *
     * @return Collection<int, Marca>
     */
    private function marcasParaFormulario(): Collection
    {
        return Marca::query()->activas()->orderBy('nombre')->get();
    }

    /**
     * Cadena de ancestros ("Electrónica / Audio") de una categoría, para
     * situarla dentro del árbol en el encabezado del listado.
     */
    private function rutaCategoria(Categoria $categoria): string
    {
        $porId = Categoria::query()->select(['id', 'padre_id', 'nombre'])->get()->keyBy('id');

        $ruta = [];
        $actual = $categoria;
        // Tope de seguridad: si un dato corrupto encadenara un ciclo, el bucle
        // pararía igual en vez de colgar la petición.
        $saltos = 0;

        while ($actual->padre_id !== null && $saltos++ < 50) {
            $padre = $porId->get($actual->padre_id);

            if ($padre === null) {
                break;
            }

            array_unshift($ruta, $padre->nombre);
            $actual = $padre;
        }

        return implode(' / ', $ruta);
    }

    public function render(): View
    {
        $termino = trim($this->buscar);

        $categoria = $this->categoriaFiltro !== null
            ? Categoria::find($this->categoriaFiltro)
            : null;

        // Entrar en una categoría muestra también lo que cuelga de ella: si no,
        // un padre con todo su catálogo repartido entre subcategorías se vería
        // vacío. Se filtra por la rama completa (la categoría y su descendencia).
        $idsRama = $this->idsRama($categoria);

        $enRama = fn ($consulta) => $consulta->whereIn('categoria_id', $idsRama);

        $productos = Producto::query()
            ->with(['categoria', 'marca'])
            // Unidades físicas listas para vender: solo las que están en
            // estado en_stock (scopeDisponibles). Las reservadas, vendidas,
            // dañadas o en garantía no cuentan como existencias.
            ->withCount(['unidades as unidades_disponibles' => fn ($q) => $q->disponibles()])
            ->when($categoria !== null, $enRama)
            ->when($this->marcaFiltro, fn ($q) => $q->where('marca_id', $this->marcaFiltro))
            ->when($termino !== '', fn ($q) => $q->where(function ($q2) use ($termino) {
                $q2->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('sku', 'like', "%{$termino}%")
                    ->orWhere('modelo', 'like', "%{$termino}%");
            }))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            ->orderBy('id')
            ->paginate(10);

        // Los indicadores acompañan al contexto: dentro de una categoría son
        // los de su rama, si no los de todo el catálogo. De otro modo el
        // encabezado contradiría a la tabla que tiene justo debajo.
        $totales = Producto::query()
            ->when($categoria !== null, $enRama)
            ->selectRaw('count(*) as total, sum(activo) as activas, sum(precio_venta) as valor')
            ->first();

        // Subcategorías directas, para ofrecerlas como acceso rápido.
        $subcategorias = $categoria !== null
            ? Categoria::query()
                ->where('padre_id', $categoria->id)
                ->withCount('productos')
                ->ordenadas()
                ->get()
            : collect();

        return view('livewire.productos.index', [
            'productos' => $productos,
            'totalProductos' => (int) $totales->total,
            'activos' => (int) $totales->activas,
            'inactivos' => (int) $totales->total - (int) $totales->activas,
            'valorCatalogo' => (float) $totales->valor,
            'opcionesCategorias' => $this->opcionesCategorias(),
            'marcas' => $this->marcasParaFiltro($idsRama),
            'marcasFormulario' => $this->marcasParaFormulario(),
            'categoria' => $categoria,
            'categoriaRuta' => $categoria !== null ? $this->rutaCategoria($categoria) : '',
            'subcategorias' => $subcategorias,
        ]);
    }
}
