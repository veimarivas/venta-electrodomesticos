<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\Persona;
use App\Support\GeneradorCodigoCliente;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de clientes.
 *
 * Misma forma que Trabajadores: la ficha del cliente solo guarda su código y
 * el vínculo 1 a 1 con `personas`, donde están todos los datos personales. El
 * alta avanza por pasos —primero se busca a la persona, y solo si no existe se
 * registra— para no duplicar a alguien que ya está en el sistema.
 *
 * Por ahora los clientes NO tienen cuenta de usuario.
 */
class Index extends Component
{
    use WithPagination;

    /** Livewire usa la paginación de Tailwind si no se le indica el tema. */
    protected string $paginationTheme = 'bootstrap';

    // ---- Listado ----------------------------------------------------------

    public string $buscar = '';

    public string $ordenarPor = 'codigo';

    public string $direccionOrden = 'asc';

    /** activos | archivados | todos */
    public string $filtroEstado = 'activos';

    // ---- Alta: el modal avanza por pasos ----------------------------------

    /** buscar → localizar a la persona | asignar → ya existe | nueva → registrarla */
    public string $paso = 'buscar';

    /** Término del buscador de personas dentro del modal de alta. */
    public string $buscarPersona = '';

    /** Persona elegida en el paso 'asignar'. */
    public ?int $personaSeleccionada = null;

    // ---- Campos de la persona nueva ---------------------------------------

    public string $carnet = '';

    public string $nombres = '';

    public string $apellido_paterno = '';

    public string $apellido_materno = '';

    public string $celular = '';

    public string $direccion = '';

    public string $correo = '';

    public string $fecha_nacimiento = '';

    // ---- Archivado --------------------------------------------------------

    /** Cliente que se va a archivar. */
    public ?int $archivarId = null;

    public string $archivarNombre = '';

    public string $archivarCodigo = '';

    /** Campos de la persona, solo en el camino 'nueva'. */
    private const CAMPOS_PERSONA = [
        'carnet',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'celular',
        'direccion',
        'correo',
        'fecha_nacimiento',
    ];

    // =======================================================================
    // Validación
    // =======================================================================

    /**
     * Al asignar a alguien que ya existe no hay nada que validar: la ficha del
     * cliente solo tiene el código, y lo genera el sistema. Solo el camino
     * 'nueva' valida datos, los de la persona.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->paso === 'nueva' ? $this->reglasPersona() : [];
    }

    /**
     * Mismas reglas que el módulo de personas: si aquí fueran más laxas,
     * se colarían datos que el otro formulario rechaza.
     *
     * @return array<string, mixed>
     */
    private function reglasPersona(): array
    {
        $soloLetras = '/^[\p{L}\s\'\-]+$/u';

        return [
            'carnet' => [
                'required', 'string', 'regex:/^[0-9]{7,11}$/',
                Rule::unique('personas', 'carnet')->whereNull('deleted_at'),
            ],
            'nombres' => ['required', 'string', 'min:2', 'max:100', "regex:$soloLetras"],
            'apellido_paterno' => ['required_without:apellido_materno', 'string', 'min:2', 'max:60', "regex:$soloLetras"],
            'apellido_materno' => ['required_without:apellido_paterno', 'string', 'min:2', 'max:60', "regex:$soloLetras"],
            'celular' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => [
                'nullable', 'email:rfc', 'max:150',
                Rule::unique('personas', 'correo')->whereNull('deleted_at'),
            ],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'carnet.regex' => 'El carnet debe contener entre 7 y 11 números.',
            'carnet.unique' => 'Ya existe una persona registrada con este carnet.',
            'nombres.regex' => 'El nombre solo puede contener letras.',
            'apellido_paterno.regex' => 'El apellido solo puede contener letras.',
            'apellido_materno.regex' => 'El apellido solo puede contener letras.',
            'apellido_paterno.required_without' => 'Debes registrar al menos un apellido.',
            'apellido_materno.required_without' => 'Debes registrar al menos un apellido.',
            'celular.regex' => 'El celular debe tener 8 números.',
            'correo.unique' => 'Ya existe una persona registrada con este correo.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'fecha_nacimiento.after' => 'La fecha de nacimiento no parece válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'carnet' => 'carnet',
            'nombres' => 'nombres',
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'celular' => 'celular',
            'direccion' => 'dirección',
            'correo' => 'correo',
            'fecha_nacimiento' => 'fecha de nacimiento',
        ];
    }

    /**
     * Validación en tiempo real, campo por campo.
     */
    public function updated(string $campo): void
    {
        if (! array_key_exists($campo, $this->rules())) {
            return;
        }

        $this->validateOnly($campo);

        // Los apellidos se validan juntos: al llenar uno debe desaparecer al
        // momento el error de "al menos un apellido" del otro.
        if ($campo === 'apellido_paterno' || $campo === 'apellido_materno') {
            $this->validateOnly($campo === 'apellido_paterno' ? 'apellido_materno' : 'apellido_paterno');
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

    /**
     * ¿Está completa la parte del formulario que toca al paso actual?
     */
    #[Computed]
    public function formularioValido(): bool
    {
        $reglas = $this->rules();

        // En el paso 'asignar' no hay nada que completar: basta con haber
        // elegido a la persona.
        if ($reglas === []) {
            return $this->personaSeleccionada !== null;
        }

        return Validator::make(
            $this->only(array_keys($reglas)),
            $reglas,
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    // =======================================================================
    // Búsqueda de personas dentro del modal de alta
    // =======================================================================

    /**
     * Resultados en vivo del buscador de personas. Se marca cuáles ya son
     * clientes para no ofrecerlas dos veces.
     *
     * @return Collection<int, Persona>
     */
    #[Computed]
    public function resultadosPersonas(): Collection
    {
        $termino = trim($this->buscarPersona);

        // Con menos de dos caracteres la búsqueda devolvería medio padrón.
        if (mb_strlen($termino) < 2) {
            return new Collection;
        }

        return Persona::query()
            ->with('cliente')
            ->buscar($termino)
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->limit(8)
            ->get();
    }

    /** ¿Se buscó algo y no apareció nadie? Entonces toca registrarla. */
    #[Computed]
    public function sinResultados(): bool
    {
        return mb_strlen(trim($this->buscarPersona)) >= 2 && $this->resultadosPersonas->isEmpty();
    }

    /** Persona elegida en el paso 'asignar'. */
    #[Computed]
    public function persona(): ?Persona
    {
        return $this->personaSeleccionada === null
            ? null
            : Persona::find($this->personaSeleccionada);
    }

    /**
     * Código que le tocaría al próximo cliente. Es una previsualización:
     * el definitivo lo asigna el generador al guardar.
     */
    #[Computed]
    public function codigoPrevisto(): string
    {
        return app(GeneradorCodigoCliente::class)->siguiente();
    }

    // =======================================================================
    // Alta
    // =======================================================================

    public function abrirCrear(): void
    {
        $this->autorizar('clientes.crear');

        $this->limpiarFormulario();
        $this->paso = 'buscar';

        $this->dispatch('abrir-modal-cliente');
    }

    /**
     * Paso 1 → 2: la persona ya existe y todavía no es cliente.
     */
    public function seleccionarPersona(int $personaId): void
    {
        $this->autorizar('clientes.crear');

        $persona = Persona::with('cliente')->findOrFail($personaId);

        // Defensa en el servidor: la vista ya deshabilita el botón, pero el
        // método es invocable directamente. Si la ficha existe pero está
        // archivada, el camino correcto es restaurarla, no crear otra: el
        // índice único de persona_id lo rechazaría igualmente.
        if ($persona->cliente !== null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta persona ya está registrada como cliente.');

            return;
        }

        if (Cliente::withTrashed()->where('persona_id', $persona->id)->exists()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta persona tiene una ficha archivada: restáurala en lugar de crear otra.');

            return;
        }

        $this->personaSeleccionada = $persona->id;
        $this->paso = 'asignar';

        $this->resetValidation();
    }

    /**
     * Paso 1 → 2b: no existe, hay que darla de alta.
     */
    public function irARegistrarPersona(): void
    {
        $this->autorizar('clientes.crear');

        $termino = trim($this->buscarPersona);

        // Lo tecleado en el buscador se aprovecha: si son solo números es un
        // carnet, si no, el nombre. Evita escribirlo dos veces.
        $this->reset(self::CAMPOS_PERSONA);

        if (preg_match('/^[0-9]{7,11}$/', $termino)) {
            $this->carnet = $termino;
        } elseif ($termino !== '') {
            $this->nombres = $termino;
        }

        $this->personaSeleccionada = null;
        $this->paso = 'nueva';

        $this->resetValidation();
    }

    /** Vuelve al buscador desde cualquiera de los dos caminos. */
    public function volverABuscar(): void
    {
        $this->personaSeleccionada = null;
        $this->paso = 'buscar';
        $this->resetValidation();
    }

    /**
     * Registra la ficha de cliente de una persona que ya estaba en el sistema.
     */
    public function asignar(): void
    {
        $this->autorizar('clientes.crear');

        if ($this->personaSeleccionada === null) {
            return;
        }

        $persona = Persona::with('cliente')->findOrFail($this->personaSeleccionada);

        if (Cliente::withTrashed()->where('persona_id', $persona->id)->exists()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta persona ya tiene ficha de cliente.');

            return;
        }

        $cliente = app(GeneradorCodigoCliente::class)->crearCon([
            'persona_id' => $persona->id,
        ]);

        $this->cerrarAlta("{$persona->nombre_completo} ahora es cliente con el código {$cliente->codigo}.");
    }

    /**
     * Registra a la persona y su ficha de cliente de una sola vez.
     */
    public function registrarPersonaYAsignar(): void
    {
        $this->autorizar('clientes.crear');

        $datos = $this->validate();

        // Los opcionales vacíos van como NULL: dos personas sin correo
        // chocarían contra el índice único si se guardara cadena vacía.
        foreach (['apellido_materno', 'celular', 'direccion', 'correo', 'fecha_nacimiento'] as $campo) {
            $datos[$campo] = $datos[$campo] === '' ? null : $datos[$campo];
        }

        // Persona y ficha se crean juntas: si falla la segunda, no debe quedar
        // una persona suelta que el usuario cree que no registró.
        $cliente = DB::transaction(function () use ($datos): Cliente {
            $persona = Persona::create([
                'carnet' => $datos['carnet'],
                'nombres' => $datos['nombres'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'apellido_materno' => $datos['apellido_materno'],
                'celular' => $datos['celular'],
                'direccion' => $datos['direccion'],
                'correo' => $datos['correo'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
            ]);

            return app(GeneradorCodigoCliente::class)->crearCon([
                'persona_id' => $persona->id,
            ]);
        });

        $this->cerrarAlta("Cliente registrado con el código {$cliente->codigo}.");
    }

    private function cerrarAlta(string $mensaje): void
    {
        $this->limpiarFormulario();
        $this->resetPage();

        $this->dispatch('cerrar-modal-cliente');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // =======================================================================
    // Archivado y restauración
    // =======================================================================

    public function confirmarArchivar(int $id): void
    {
        $this->autorizar('clientes.eliminar');

        $cliente = Cliente::with('persona')->findOrFail($id);

        $this->archivarId = $cliente->id;
        $this->archivarNombre = $cliente->persona->nombre_completo;
        $this->archivarCodigo = $cliente->codigo;

        $this->dispatch('abrir-modal-archivar-cliente');
    }

    /**
     * Archivar NO borra la ficha ni a la persona: la marca con softDelete.
     *
     * El registro tiene que permanecer porque las ventas que se implementen
     * después seguirán apuntando a este cliente. Borrarlo dejaría el histórico
     * huérfano, igual que pasa con los trabajadores dados de baja.
     */
    public function archivar(): void
    {
        $this->autorizar('clientes.eliminar');

        if ($this->archivarId === null) {
            return;
        }

        Cliente::findOrFail($this->archivarId)->delete();

        $this->reset(['archivarId', 'archivarNombre', 'archivarCodigo']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-archivar-cliente');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Cliente archivado. Su historial se conserva.');
    }

    /**
     * Devuelve al listado activo un cliente archivado, conservando su código.
     */
    public function restaurar(int $id): void
    {
        $this->autorizar('clientes.editar');

        $cliente = Cliente::withTrashed()->with('persona')->findOrFail($id);

        if (! $cliente->trashed()) {
            return;
        }

        $cliente->restore();

        $this->dispatch('toast', tipo: 'success', mensaje: "{$cliente->persona->nombre_completo} vuelve al listado con el código {$cliente->codigo}.");
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

    public function limpiarFormulario(): void
    {
        $this->reset([
            ...self::CAMPOS_PERSONA,
            'personaSeleccionada',
            'buscarPersona',
        ]);

        $this->paso = 'buscar';
        $this->resetValidation();
    }

    public function render(): View
    {
        $clientes = Cliente::query()
            ->with('persona')
            ->when($this->filtroEstado === 'archivados', fn ($q) => $q->onlyTrashed())
            ->when($this->filtroEstado === 'todos', fn ($q) => $q->withTrashed())
            ->buscar($this->buscar)
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.clientes.index', [
            'clientes' => $clientes,
            'totalActivos' => Cliente::count(),
            'totalArchivados' => Cliente::onlyTrashed()->count(),
            'conCorreo' => Cliente::whereHas('persona', fn ($q) => $q->whereNotNull('correo'))->count(),
            'altasDelMes' => Cliente::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ]);
    }
}
