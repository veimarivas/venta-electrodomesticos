<?php

namespace App\Livewire\Trabajadores;

use App\Models\Cargo;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Models\User;
use App\Support\CuentaDeTrabajador;
use App\Support\GeneradorCodigoTrabajador;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    use WithPagination;

    /** Livewire usa la paginación de Tailwind si no se le indica el tema. */
    protected string $paginationTheme = 'bootstrap';

    // ---- Listado ----------------------------------------------------------

    public string $buscar = '';

    public string $ordenarPor = 'codigo';

    public string $direccionOrden = 'asc';

    /** activos | baja | todos */
    public string $filtroEstado = 'activos';

    // ---- Alta: el modal avanza por pasos ----------------------------------

    /** buscar → localizar a la persona | asignar → ya existe | nueva → registrarla */
    public string $paso = 'buscar';

    /** Término del buscador de personas dentro del modal de alta. */
    public string $buscarPersona = '';

    /** Persona elegida en el paso 'asignar'. */
    public ?int $personaSeleccionada = null;

    // ---- Campos de la ficha laboral ---------------------------------------

    public string $cargo_id = '';

    public string $fecha_ingreso = '';

    // ---- Campos de la persona nueva ---------------------------------------

    public string $carnet = '';

    public string $nombres = '';

    public string $apellido_paterno = '';

    public string $apellido_materno = '';

    public string $celular = '';

    public string $direccion = '';

    public string $correo = '';

    public string $fecha_nacimiento = '';

    // ---- Edición y baja ---------------------------------------------------

    public ?int $trabajadorId = null;

    /** Trabajador al que se va a dar de baja. */
    public ?int $bajaId = null;

    public string $bajaNombre = '';

    public string $bajaCodigo = '';

    public string $motivo_baja = '';

    // ---- Cuenta de acceso -------------------------------------------------

    /** Trabajador cuya cuenta se está creando o reiniciando. */
    public ?int $cuentaTrabajadorId = null;

    public string $cuentaNombre = '';

    /** Previsualización de lo que se va a crear (no son campos editables). */
    public string $cuentaUsuario = '';

    public string $cuentaCorreo = '';

    public string $cuentaPassword = '';

    /** ¿El correo de acceso es el de la persona o uno interno generado? */
    public bool $cuentaCorreoPropio = false;

    /** Rol que se asignará a la cuenta nueva. */
    public string $cuentaRol = CuentaDeTrabajador::ROL_POR_DEFECTO;

    /** Campos de la ficha laboral, comunes a los dos caminos de alta. */
    private const CAMPOS_LABORALES = ['cargo_id', 'fecha_ingreso'];

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
     * Las reglas dependen del paso: al asignar a alguien que ya existe solo
     * se valida la ficha laboral; al registrar a alguien nuevo, también sus
     * datos personales.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->paso === 'nueva'
            ? [...$this->reglasPersona(), ...$this->reglasLaborales()]
            : $this->reglasLaborales();
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasLaborales(): array
    {
        return [
            'cargo_id' => ['required', 'integer', Rule::exists('cargos', 'id')],
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today', 'after:1950-01-01'],
        ];
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
            'cargo_id.required' => 'Debes elegir un cargo.',
            'cargo_id.exists' => 'El cargo seleccionado ya no existe.',
            'fecha_ingreso.required' => 'Debes indicar la fecha de ingreso.',
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'fecha_ingreso.after' => 'La fecha de ingreso no parece válida.',
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
            'cargo_id' => 'cargo',
            'fecha_ingreso' => 'fecha de ingreso',
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

    /**
     * ¿Está completa la parte del formulario que toca al paso actual?
     */
    #[Computed]
    public function formularioValido(): bool
    {
        $reglas = $this->rules();

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
     * trabajadores para no ofrecerlas dos veces.
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
            ->with('trabajador.cargo')
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
     * Código que le tocaría al próximo trabajador. Es una previsualización:
     * el definitivo lo asigna el generador al guardar.
     */
    #[Computed]
    public function codigoPrevisto(): string
    {
        return app(GeneradorCodigoTrabajador::class)->siguiente();
    }

    /** Cargos disponibles para el selector. */
    #[Computed]
    public function cargos(): Collection
    {
        return Cargo::orderBy('nombre')->get();
    }

    // =======================================================================
    // Alta
    // =======================================================================

    public function abrirCrear(): void
    {
        $this->autorizar('trabajadores.crear');

        $this->limpiarFormulario();
        $this->paso = 'buscar';

        $this->dispatch('abrir-modal-trabajador');
    }

    /**
     * Paso 1 → 2: la persona ya existe y todavía no es trabajador.
     */
    public function seleccionarPersona(int $personaId): void
    {
        $this->autorizar('trabajadores.crear');

        $persona = Persona::with('trabajador')->findOrFail($personaId);

        // Defensa en el servidor: la vista ya deshabilita el botón, pero el
        // método es invocable directamente. Si la ficha existe pero está dada
        // de baja, el camino correcto es reactivarla, no crear otra.
        if ($persona->trabajador !== null) {
            $mensaje = $persona->trabajador->esta_activo
                ? 'Esta persona ya está registrada como trabajador.'
                : 'Esta persona ya tiene ficha: usa «Reincorporar» en lugar de crear una nueva.';

            $this->dispatch('toast', tipo: 'error', mensaje: $mensaje);

            return;
        }

        $this->personaSeleccionada = $persona->id;
        $this->paso = 'asignar';
        $this->fecha_ingreso = now()->format('Y-m-d');

        $this->resetValidation();
    }

    /**
     * Paso 1 → 2b: no existe, hay que darla de alta.
     */
    public function irARegistrarPersona(): void
    {
        $this->autorizar('trabajadores.crear');

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
        $this->fecha_ingreso = now()->format('Y-m-d');
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
     * Registra la ficha laboral de una persona que ya estaba en el sistema.
     */
    public function asignar(): void
    {
        $this->autorizar('trabajadores.crear');

        if ($this->personaSeleccionada === null) {
            return;
        }

        $datos = $this->validate($this->reglasLaborales());

        $persona = Persona::with('trabajador')->findOrFail($this->personaSeleccionada);

        if ($persona->trabajador !== null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta persona ya está registrada como trabajador.');

            return;
        }

        $trabajador = app(GeneradorCodigoTrabajador::class)->crearCon([
            'persona_id' => $persona->id,
            'cargo_id' => (int) $datos['cargo_id'],
            'fecha_ingreso' => $datos['fecha_ingreso'],
        ]);

        $this->cerrarAlta("{$persona->nombre_completo} ahora es trabajador con el código {$trabajador->codigo}.");
    }

    /**
     * Registra a la persona y su ficha laboral de una sola vez.
     */
    public function registrarPersonaYAsignar(): void
    {
        $this->autorizar('trabajadores.crear');

        $datos = $this->validate();

        // Los opcionales vacíos van como NULL: dos personas sin correo
        // chocarían contra el índice único si se guardara cadena vacía.
        foreach (['apellido_materno', 'celular', 'direccion', 'correo', 'fecha_nacimiento'] as $campo) {
            $datos[$campo] = $datos[$campo] === '' ? null : $datos[$campo];
        }

        // Persona y ficha laboral se crean juntas: si falla la segunda, no
        // debe quedar una persona suelta que el usuario cree que no registró.
        $trabajador = DB::transaction(function () use ($datos): Trabajador {
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

            return app(GeneradorCodigoTrabajador::class)->crearCon([
                'persona_id' => $persona->id,
                'cargo_id' => (int) $datos['cargo_id'],
                'fecha_ingreso' => $datos['fecha_ingreso'],
            ]);
        });

        $this->cerrarAlta("Trabajador registrado con el código {$trabajador->codigo}.");
    }

    private function cerrarAlta(string $mensaje): void
    {
        $this->limpiarFormulario();
        $this->resetPage();

        $this->dispatch('cerrar-modal-trabajador');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // =======================================================================
    // Edición y baja
    // =======================================================================

    /**
     * Solo se editan cargo y fecha de ingreso. El código es la identidad del
     * trabajador dentro del histórico, así que no se toca; los datos
     * personales se corrigen desde el módulo de personas.
     */
    public function abrirEditar(int $id): void
    {
        $this->autorizar('trabajadores.editar');

        $trabajador = Trabajador::findOrFail($id);

        $this->trabajadorId = $trabajador->id;
        $this->cargo_id = (string) $trabajador->cargo_id;
        $this->fecha_ingreso = $trabajador->fecha_ingreso->format('Y-m-d');
        $this->paso = 'editar';

        $this->resetValidation();
        $this->dispatch('abrir-modal-editar-trabajador');
    }

    public function guardarEdicion(): void
    {
        $this->autorizar('trabajadores.editar');

        $datos = $this->validate($this->reglasLaborales());

        Trabajador::findOrFail($this->trabajadorId)->update([
            'cargo_id' => (int) $datos['cargo_id'],
            'fecha_ingreso' => $datos['fecha_ingreso'],
        ]);

        $this->limpiarFormulario();

        $this->dispatch('cerrar-modal-editar-trabajador');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Ficha del trabajador actualizada correctamente.');
    }

    public function confirmarBaja(int $id): void
    {
        $this->autorizar('trabajadores.eliminar');

        $trabajador = Trabajador::with('persona')->findOrFail($id);

        $this->bajaId = $trabajador->id;
        $this->bajaNombre = $trabajador->persona->nombre_completo;
        $this->bajaCodigo = $trabajador->codigo;
        $this->motivo_baja = '';

        $this->dispatch('abrir-modal-baja-trabajador');
    }

    /**
     * La baja NO borra la ficha: la marca con fecha y motivo.
     *
     * El registro tiene que permanecer porque las ventas, compras y
     * movimientos de inventario que se implementen después seguirán
     * apuntando a este trabajador. Borrarlo dejaría el histórico huérfano.
     */
    public function darDeBaja(): void
    {
        $this->autorizar('trabajadores.eliminar');

        if ($this->bajaId === null) {
            return;
        }

        $trabajador = Trabajador::with('persona.user')->findOrFail($this->bajaId);

        if (! $trabajador->esta_activo) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Este trabajador ya estaba dado de baja.');

            return;
        }

        // Darse de baja a uno mismo cerraría la sesión en el acto (el
        // middleware 'active' expulsa a las cuentas desactivadas) y dejaría al
        // administrador fuera del sistema a mitad de la operación.
        if ($trabajador->persona->user?->is($this->usuarioActual())) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No puedes darte de baja a ti mismo. Pídeselo a otro administrador.');

            return;
        }

        // Ficha y cuenta se cierran juntas: un trabajador dado de baja que
        // sigue pudiendo entrar al sistema es exactamente lo que la baja
        // pretende evitar. La cuenta NO se borra, solo se desactiva, porque
        // las ventas y compras que registró siguen apuntando a ella.
        $cuentaDesactivada = DB::transaction(function () use ($trabajador): bool {
            $trabajador->update([
                'fecha_baja' => now()->toDateString(),
                'motivo_baja' => trim($this->motivo_baja) !== '' ? trim($this->motivo_baja) : null,
            ]);

            $cuenta = $trabajador->persona->user;

            if ($cuenta === null || ! $cuenta->is_active) {
                return false;
            }

            $cuenta->update(['is_active' => false]);

            return true;
        });

        $this->reset(['bajaId', 'bajaNombre', 'bajaCodigo', 'motivo_baja']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-baja-trabajador');
        $this->dispatch('toast', tipo: 'success', mensaje: $cuentaDesactivada
            ? 'Trabajador dado de baja y su cuenta bloqueada. Su historial se conserva.'
            : 'Trabajador dado de baja. Su historial se conserva.');
    }

    /**
     * Vuelve a incorporar a alguien que había sido dado de baja. Conserva su
     * código y su fecha de ingreso original.
     */
    public function reactivar(int $id): void
    {
        $this->autorizar('trabajadores.editar');

        $trabajador = Trabajador::with('persona.user')->findOrFail($id);

        if ($trabajador->esta_activo) {
            return;
        }

        // Simétrico a la baja: si tenía cuenta, vuelve a quedar operativa. Sin
        // esto, reincorporar a alguien lo dejaría sin poder entrar y habría que
        // acordarse de reactivarlo a mano desde el módulo de usuarios.
        $cuentaReactivada = DB::transaction(function () use ($trabajador): bool {
            $trabajador->update(['fecha_baja' => null, 'motivo_baja' => null]);

            $cuenta = $trabajador->persona->user;

            if ($cuenta === null || $cuenta->is_active) {
                return false;
            }

            $cuenta->update(['is_active' => true]);

            return true;
        });

        $this->dispatch('toast', tipo: 'success', mensaje: $cuentaReactivada
            ? "{$trabajador->persona->nombre_completo} fue reincorporado ({$trabajador->codigo}) y su cuenta reactivada."
            : "{$trabajador->persona->nombre_completo} fue reincorporado con el código {$trabajador->codigo}.");
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    // =======================================================================
    // Cuenta de acceso
    // =======================================================================
    //
    // La cuenta cuelga de la persona (users.persona_id), que es la relación
    // que ya existía: trabajador → persona → user. No se agrega ninguna
    // relación nueva ni se toca el esquema.

    /**
     * Abre la confirmación de alta de cuenta con los datos ya calculados, para
     * que el usuario vea exactamente qué se va a crear antes de aceptar.
     */
    public function confirmarCrearCuenta(int $id): void
    {
        $this->autorizar('usuarios.crear');

        $trabajador = Trabajador::with('persona.user')->findOrFail($id);

        if ($trabajador->persona->user !== null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Este trabajador ya tiene cuenta de usuario.');

            return;
        }

        // Un trabajador dado de baja ya no debe poder entrar al sistema.
        if (! $trabajador->esta_activo) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se crean cuentas para trabajadores dados de baja.');

            return;
        }

        $servicio = app(CuentaDeTrabajador::class);

        $this->cuentaTrabajadorId = $trabajador->id;
        $this->cuentaNombre = $trabajador->persona->nombre_completo;
        $this->cuentaUsuario = $servicio->usuario($trabajador->persona);
        $this->cuentaCorreo = $servicio->correo($trabajador->persona, $this->cuentaUsuario);
        $this->cuentaPassword = $servicio->passwordDe($trabajador->persona);
        $this->cuentaCorreoPropio = trim((string) $trabajador->persona->correo) !== '';
        $this->cuentaRol = CuentaDeTrabajador::ROL_POR_DEFECTO;

        $this->dispatch('abrir-modal-cuenta-trabajador');
    }

    public function crearCuenta(): void
    {
        $this->autorizar('usuarios.crear');

        if ($this->cuentaTrabajadorId === null) {
            return;
        }

        $trabajador = Trabajador::with('persona.user')->findOrFail($this->cuentaTrabajadorId);

        // Se vuelve a comprobar aquí: entre abrir el modal y aceptar, otro
        // usuario pudo haber creado la cuenta desde el módulo de usuarios.
        if ($trabajador->persona->user !== null) {
            $this->cerrarCuenta();
            $this->dispatch('toast', tipo: 'error', mensaje: 'Este trabajador ya tiene cuenta de usuario.');

            return;
        }

        if (! $trabajador->esta_activo) {
            $this->cerrarCuenta();
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se crean cuentas para trabajadores dados de baja.');

            return;
        }

        $this->validate(
            ['cuentaRol' => ['required', 'string', Rule::exists('roles', 'name')]],
            ['cuentaRol.required' => 'Debes elegir el rol de la cuenta.'],
            ['cuentaRol' => 'rol']
        );

        $usuario = app(CuentaDeTrabajador::class)->crear($trabajador->persona, $this->cuentaRol);

        $this->cerrarCuenta();
        $this->dispatch('toast', tipo: 'success', mensaje: "Cuenta «{$usuario->name}» creada. La contraseña es el carnet.");
    }

    /**
     * Abre la confirmación de reinicio de contraseña.
     */
    public function confirmarReiniciarPassword(int $id): void
    {
        $this->autorizar('usuarios.editar');

        $trabajador = Trabajador::with('persona.user')->findOrFail($id);

        if ($trabajador->persona->user === null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Este trabajador todavía no tiene cuenta de usuario.');

            return;
        }

        $servicio = app(CuentaDeTrabajador::class);

        $this->cuentaTrabajadorId = $trabajador->id;
        $this->cuentaNombre = $trabajador->persona->nombre_completo;
        // El nombre de usuario se realinea con la convención por si los datos
        // de la persona cambiaron; el correo de acceso se conserva.
        $this->cuentaUsuario = $servicio->usuario($trabajador->persona, $trabajador->persona->user->id);
        $this->cuentaCorreo = (string) $trabajador->persona->user->email;
        $this->cuentaPassword = $servicio->passwordDe($trabajador->persona);

        $this->dispatch('abrir-modal-reiniciar-password');
    }

    public function reiniciarPassword(): void
    {
        $this->autorizar('usuarios.editar');

        if ($this->cuentaTrabajadorId === null) {
            return;
        }

        $trabajador = Trabajador::with('persona.user')->findOrFail($this->cuentaTrabajadorId);
        $usuario = $trabajador->persona->user;

        if ($usuario === null) {
            $this->cerrarCuenta('cerrar-modal-reiniciar-password');
            $this->dispatch('toast', tipo: 'error', mensaje: 'Este trabajador todavía no tiene cuenta de usuario.');

            return;
        }

        app(CuentaDeTrabajador::class)->reiniciar($usuario, $trabajador->persona);

        $this->cerrarCuenta('cerrar-modal-reiniciar-password');
        $this->dispatch('toast', tipo: 'success', mensaje: "Contraseña de «{$usuario->name}» reiniciada al carnet.");
    }

    private function cerrarCuenta(string $evento = 'cerrar-modal-cuenta-trabajador'): void
    {
        $this->reset([
            'cuentaTrabajadorId',
            'cuentaNombre',
            'cuentaUsuario',
            'cuentaCorreo',
            'cuentaPassword',
            'cuentaCorreoPropio',
            'cuentaRol',
        ]);

        $this->resetValidation();
        $this->dispatch($evento);
    }

    /** Roles disponibles para el selector del alta de cuenta. */
    #[Computed]
    public function rolesDisponibles(): Collection
    {
        return Role::orderBy('name')->get();
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

    private function usuarioActual(): ?User
    {
        return auth()->user();
    }

    public function limpiarFormulario(): void
    {
        $this->reset([
            ...self::CAMPOS_PERSONA,
            ...self::CAMPOS_LABORALES,
            'personaSeleccionada',
            'buscarPersona',
            'trabajadorId',
        ]);

        $this->paso = 'buscar';
        $this->resetValidation();
    }

    public function render(): View
    {
        $trabajadores = Trabajador::query()
            // persona.user es la relación que ya existe (users.persona_id):
            // de ahí sale si el trabajador tiene o no cuenta de acceso.
            ->with(['persona.user', 'cargo'])
            ->buscar($this->buscar)
            ->when($this->filtroEstado === 'activos', fn ($q) => $q->activos())
            ->when($this->filtroEstado === 'baja', fn ($q) => $q->dadosDeBaja())
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.trabajadores.index', [
            'trabajadores' => $trabajadores,
            'totalActivos' => Trabajador::activos()->count(),
            'totalBajas' => Trabajador::dadosDeBaja()->count(),
            'totalCargos' => Cargo::count(),
            'ingresosDelMes' => Trabajador::whereYear('fecha_ingreso', now()->year)
                ->whereMonth('fecha_ingreso', now()->month)
                ->count(),
        ]);
    }
}
