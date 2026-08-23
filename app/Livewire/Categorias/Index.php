<?php

namespace App\Livewire\Categorias;

use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    /** Buscador del listado. */
    public string $buscar = '';

    /** Id de la categoría en edición; null significa "registro nuevo". */
    public ?int $categoriaId = null;

    /** Id de la categoría pendiente de eliminar. */
    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    // ---- Campos del formulario -------------------------------------------

    public string $nombre = '';

    public string $slug = '';

    public ?int $padreId = null;

    public string $descripcion = '';

    public int $posicion = 0;

    public bool $isActive = true;

    /**
     * Si el usuario editó el slug a mano, no se vuelve a generar cuando
     * cambia el nombre.
     */
    public bool $slugManual = false;

    /**
     * Cuando se crea una subcategoría desde el botón de la fila, el padre
     * viene preseleccionado y el select debe quedar bloqueado.
     */
    public bool $padreForzado = false;

    /**
     * Campos que forman el formulario, para validar y poblar en bloque.
     * Nota: las propiedades padreId/isActive se mapean a padre_id/activo
     * (columnas de la base de datos) en guardar().
     */
    private const CAMPOS = [
        'nombre',
        'slug',
        'padreId',
        'descripcion',
        'posicion',
        'isActive',
    ];

    /**
     * Reglas de validación. El slug es único ignorando el propio registro al
     * editar; la categoría padre no puede ser sí misma ni una descendiente.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $padresProhibidos = [];

        if ($this->categoriaId !== null) {
            $padresProhibidos = Categoria::find($this->categoriaId)?->descendientesIds() ?? [];
            $padresProhibidos[] = $this->categoriaId;
        }

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => [
                'required', 'string', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('categorias', 'slug')->ignore($this->categoriaId)->whereNull('deleted_at'),
            ],
            'padreId' => [
                'nullable', 'integer',
                Rule::exists('categorias', 'id')->whereNull('deleted_at'),
                Rule::notIn($padresProhibidos),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'posicion' => ['integer', 'min:0', 'max:999'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * Mensajes específicos donde el genérico traducido no es suficientemente claro.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'slug.required' => 'El slug es obligatorio.',
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una categoría con este slug.',
            'padreId.exists' => 'La categoría padre ya no existe.',
            'padreId.not_in' => 'No puedes mover la categoría dentro de sí misma o de una subcategoría.',
        ];
    }

    /**
     * Nombres de campo en español para los mensajes de validación.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'slug' => 'slug',
            'padreId' => 'categoría padre',
            'descripcion' => 'descripción',
            'posicion' => 'posición',
            'isActive' => 'estado',
        ];
    }

    /**
     * Al cambiar el nombre se regenera el slug, salvo que el usuario ya lo
     * haya personalizado a mano.
     */
    public function updatedNombre(): void
    {
        if (! $this->slugManual) {
            $this->slug = Str::slug($this->nombre);
        }
    }

    /**
     * En cuanto el usuario toca el slug, deja de regenerarse desde el nombre.
     */
    public function updatedSlug(): void
    {
        $this->slugManual = true;
    }

    /**
     * Validación en tiempo real: cada vez que cambia un campo se valida solo
     * ese campo, para no llenar el formulario de errores prematuros.
     */
    public function updated(string $campo): void
    {
        if (in_array($campo, self::CAMPOS, true)) {
            $this->validateOnly($campo);
        }
    }

    /**
     * ¿El formulario completo es válido? De esto depende que el botón de
     * guardar esté habilitado. Valida en silencio, sin publicar errores.
     */
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

    // ---- Alta y edición ---------------------------------------------------

    public function abrirCrear(): void
    {
        $this->autorizar('categorias.crear');

        $this->limpiarFormulario();
        $this->dispatch('abrir-modal-categoria');
    }

    /**
     * Abre el formulario de nueva categoría con el padre preseleccionado
     * desde el botón "+" en la fila de una categoría existente.
     */
    public function abrirCrearHijo(int $padreId): void
    {
        $this->autorizar('categorias.crear');

        $this->limpiarFormulario();
        $this->padreId = $padreId;
        $this->padreForzado = true;
        $this->dispatch('abrir-modal-categoria');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('categorias.editar');

        $categoria = Categoria::findOrFail($id);

        $this->categoriaId = $categoria->id;
        $this->nombre = $categoria->nombre;
        $this->slug = $categoria->slug;
        $this->padreId = $categoria->padre_id;
        $this->descripcion = (string) $categoria->descripcion;
        $this->posicion = $categoria->posicion;
        $this->isActive = $categoria->activo;

        // Al editar se conserva el slug existente aunque cambie el nombre.
        $this->slugManual = true;

        $this->resetValidation();
        $this->dispatch('abrir-modal-categoria');
    }

    public function guardar(): void
    {
        $this->autorizar($this->categoriaId !== null ? 'categorias.editar' : 'categorias.crear');

        $validados = $this->validate();

        // El formulario usa nombre/descripcion y la propiedad padreId, pero
        // la tabla espera nombre/descripcion y padre_id: se mapea aquí.
        $datos = [
            'nombre' => $validados['nombre'],
            'slug' => $validados['slug'],
            'padre_id' => $this->padreId ?: null,
            'descripcion' => $validados['descripcion'],
            'posicion' => $validados['posicion'],
            'activo' => (bool) $this->isActive,
        ];

        if (! $this->slugManual) {
            $datos['slug'] = Str::slug($this->nombre);
        }

        if ($this->categoriaId !== null) {
            Categoria::findOrFail($this->categoriaId)->update($datos);
            $mensaje = 'Categoría actualizada correctamente.';
        } else {
            Categoria::create($datos);
            $mensaje = 'Categoría creada correctamente.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-categoria');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // ---- Reordenamiento por arrastre --------------------------------------

    /**
     * Recoloca una categoría dentro del árbol tras soltarla en la tabla.
     *
     * El navegador solo sabe dónde se soltó; aquí se traduce a los dos datos
     * que define la jerarquía: de quién cuelga ($padreId, null = raíz) y en qué
     * lugar queda entre sus hermanos ($indice, base 0). Las posiciones de todos
     * los hermanos se reescriben de forma correlativa para que no queden huecos
     * ni empates que dejen el orden a merced del desempate por nombre.
     */
    public function moverCategoria(int $id, ?int $padreId, int $indice): void
    {
        $this->autorizar('categorias.editar');

        $categoria = Categoria::findOrFail($id);

        if ($padreId !== null) {
            if (! Categoria::whereKey($padreId)->exists()) {
                $this->dispatch('toast', tipo: 'error', mensaje: 'La categoría de destino ya no existe.');

                return;
            }

            // Misma regla que el selector del formulario: colgar una categoría
            // de sí misma o de una hija dejaría el árbol en un ciclo.
            if ($padreId === $categoria->id || in_array($padreId, $categoria->descendientesIds(), true)) {
                $this->dispatch('toast', tipo: 'warning', mensaje: 'No puedes mover la categoría dentro de sí misma o de una subcategoría.');

                return;
            }
        }

        // Hermanos del destino sin contar la categoría movida: sobre esta lista
        // se inserta en $indice, así el cálculo vale tanto si cambia de padre
        // como si solo se reordena dentro del mismo.
        $orden = Categoria::query()
            ->when(
                $padreId === null,
                fn ($consulta) => $consulta->whereNull('padre_id'),
                fn ($consulta) => $consulta->where('padre_id', $padreId)
            )
            ->whereKeyNot($categoria->id)
            ->ordenadas()
            ->pluck('id')
            ->all();

        $indice = max(0, min($indice, count($orden)));
        array_splice($orden, $indice, 0, [$categoria->id]);

        DB::transaction(function () use ($orden, $categoria, $padreId): void {
            foreach ($orden as $posicion => $idHermano) {
                $datos = ['posicion' => $posicion];

                if ($idHermano === $categoria->id) {
                    $datos['padre_id'] = $padreId;
                }

                Categoria::whereKey($idHermano)->update($datos);
            }
        });

        $this->dispatch('toast', tipo: 'success', mensaje: 'Categoría reubicada correctamente.');
    }

    // ---- Navegación -------------------------------------------------------

    /**
     * Abre el listado de productos de la categoría. La categoría se pasa por
     * sesión en vez de por query string (?categoria=N) para que la URL no
     * exponga ids internos; el listado de productos la consume al montar.
     */
    public function verProductos(int $id): void
    {
        abort_unless(auth()->user()?->can('productos.ver') ?? false, 403);

        session()->put('categoria_activa', $id);

        $this->redirect(route('productos.index'));
    }

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('categorias.eliminar');

        $categoria = Categoria::withCount('hijos')->findOrFail($id);

        if ($categoria->hijos_count > 0) {
            $this->dispatch('toast', tipo: 'warning', mensaje: 'Esta categoría tiene subcategorías. Muévelas o elimínalas primero.');

            return;
        }

        $this->eliminarId = $categoria->id;
        $this->eliminarNombre = $categoria->nombre;

        $this->dispatch('abrir-modal-eliminar-categoria');
    }

    public function eliminar(): void
    {
        $this->autorizar('categorias.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $categoria = Categoria::findOrFail($this->eliminarId);

        // Segunda comprobación: un componente Livewire es un endpoint y el
        // botón de confirmación no es la única vía para llamar a eliminar().
        if ($categoria->hijos()->exists()) {
            $this->dispatch('cerrar-modal-eliminar-categoria');
            $this->dispatch('toast', tipo: 'warning', mensaje: 'Esta categoría tiene subcategorías. Muévelas o elimínalas primero.');

            return;
        }

        $categoria->delete();

        $this->reset(['eliminarId', 'eliminarNombre']);
        $this->dispatch('cerrar-modal-eliminar-categoria');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Categoría eliminada correctamente.');
    }

    /**
     * Los botones se ocultan en la vista según el permiso, pero un componente
     * Livewire es un endpoint: cualquiera puede invocar sus métodos. La
     * comprobación tiene que estar también aquí.
     */
    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'categoriaId']);
        $this->slugManual = false;
        $this->padreForzado = false;
        $this->resetValidation();
    }

    // ---- Estructura de árbol ----------------------------------------------

    /**
     * Convierte la lista plana en un árbol anidado ordenado por posición.
     * No usa el paquete kalnoy/nestedset (ver PLAN.md): con el volumen de
     * categorías de un catálogo alcanza un groupBy en memoria.
     *
     * @param  Collection<int, Categoria>  $categorias
     * @return array<int, array{categoria: Categoria, hijos: array}>
     */
    private function construirArbol(Collection $categorias): array
    {
        $porPadre = $categorias->groupBy(fn (Categoria $c): int => $c->padre_id ?? 0);

        $construir = function (int $padreId) use ($porPadre, &$construir): array {
            $nodos = $porPadre->get($padreId, collect());

            return $nodos->map(fn (Categoria $c): array => [
                'categoria' => $c,
                'hijos' => $construir($c->id),
            ])->values()->all();
        };

        return $construir(0);
    }

    /**
     * Aplana el árbol en filas con su profundidad, tal como las consume la
     * tabla de la vista.
     *
     * @param  array<int, array{categoria: Categoria, hijos: array}>  $arbol
     * @return array<int, array<string, mixed>>
     */
    private function aplanar(array $arbol, int $nivel = 0): array
    {
        $filas = [];

        foreach ($arbol as $nodo) {
            $categoria = $nodo['categoria'];

            $filas[] = [
                'categoria' => $categoria,
                'profundidad' => $nivel,
                'tieneHijos' => $categoria->hijos_count > 0,
                'numHijos' => $categoria->hijos_count,
                'numProductos' => $categoria->productos_count,
            ];

            $filas = array_merge($filas, $this->aplanar($nodo['hijos'], $nivel + 1));
        }

        return $filas;
    }

    /**
     * Cadena de ancestros ("Electrónica / Audio") para mostrar la ruta de una
     * categoría en los resultados de búsqueda.
     *
     * @param  Collection<int, Categoria>  $todas
     */
    private function rutaAncestros(Categoria $categoria, Collection $todas): string
    {
        $porId = $todas->keyBy('id');
        $ruta = [];
        $actual = $categoria;

        while ($actual->padre_id !== null) {
            $padre = $porId->get($actual->padre_id);

            if ($padre === null) {
                break;
            }

            array_unshift($ruta, $padre->nombre);

            if ($padre->id === $categoria->id) {
                break;
            }

            $actual = $padre;
        }

        return implode(' / ', $ruta);
    }

    /**
     * Opciones del selector de categoría padre. Al editar se excluyen la
     * propia categoría y todas sus descendientes para evitar ciclos.
     *
     * @param  Collection<int, Categoria>  $categorias
     * @return array<int, array{id: int, nombre: string, nivel: int}>
     */
    private function opcionesPadre(Collection $categorias): array
    {
        $excluir = [];

        if ($this->categoriaId !== null) {
            $excluir = array_merge(
                [$this->categoriaId],
                Categoria::find($this->categoriaId)?->descendientesIds() ?? []
            );
        }

        $porPadre = $categorias->groupBy(fn (Categoria $c): int => $c->padre_id ?? 0);
        $opciones = [];

        $recorrer = function (int $padreId, int $nivel) use ($porPadre, &$recorrer, &$opciones, $excluir): void {
            foreach ($porPadre->get($padreId, collect()) as $categoria) {
                if (in_array($categoria->id, $excluir, true)) {
                    continue;
                }

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

    public function render(): View
    {
        $buscar = trim($this->buscar);

        $categorias = Categoria::query()
            ->withCount(['hijos', 'productos'])
            ->ordenadas()
            ->get();

        if ($buscar === '') {
            $filas = $this->aplanar($this->construirArbol($categorias));
            $maxProfundidad = $filas === [] ? 0 : max(array_column($filas, 'profundidad'));
        } else {
            $termino = mb_strtolower($buscar);

            $filas = $categorias
                ->filter(fn (Categoria $c): bool => Str::contains(mb_strtolower($c->nombre), $termino)
                    || Str::contains(mb_strtolower((string) $c->descripcion), $termino))
                ->map(fn (Categoria $c): array => [
                    'categoria' => $c,
                    'profundidad' => 0,
                    'tieneHijos' => $c->hijos_count > 0,
                    'numHijos' => $c->hijos_count,
                    'numProductos' => $c->productos_count,
                    'ruta' => $this->rutaAncestros($c, $categorias),
                ])
                ->values()
                ->all();

            $maxProfundidad = 0;
        }

        return view('livewire.categorias.index', [
            'filas' => $filas,
            // Totales del encabezado: son del universo completo, no del filtro.
            'totalCategorias' => $categorias->count(),
            'activas' => $categorias->where('activo', true)->count(),
            'inactivas' => $categorias->where('activo', false)->count(),
            'maxProfundidad' => $maxProfundidad,
            'opcionesPadre' => $this->opcionesPadre($categorias),
            // Al buscar se muestra una lista plana sin jerarquía: arrastrar ahí
            // no tendría un "antes/después" ni un padre que interpretar.
            'puedeOrdenar' => $buscar === '' && (auth()->user()?->can('categorias.editar') ?? false),
        ]);
    }
}
