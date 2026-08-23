<?php

namespace App\Livewire\Personas;

use App\Models\Persona;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /**
     * Livewire trae su propia vista de paginación y por defecto usa la de
     * Tailwind, ignorando el Paginator::useBootstrapFive() global. Hay que
     * declararle el tema aquí para que los enlaces salgan con las clases de
     * Bootstrap que entiende Velzon.
     */
    protected string $paginationTheme = 'bootstrap';

    /** Buscador del listado. */
    public string $buscar = '';

    /** Ordenamiento del listado. */
    public string $ordenarPor = 'apellido_paterno';

    public string $direccionOrden = 'asc';

    /** Id de la persona en edición; null significa "registro nuevo". */
    public ?int $personaId = null;

    /** Id de la persona pendiente de eliminar. */
    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    // ---- Campos del formulario -------------------------------------------

    public string $carnet = '';

    public string $nombres = '';

    public string $apellido_paterno = '';

    public string $apellido_materno = '';

    public string $celular = '';

    public string $direccion = '';

    public string $correo = '';

    public string $fecha_nacimiento = '';

    /** Campos que forman el formulario, para validar y poblar en bloque. */
    private const CAMPOS = [
        'carnet',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'celular',
        'direccion',
        'correo',
        'fecha_nacimiento',
    ];

    /**
     * Reglas de validación. Las reglas 'unique' ignoran el propio registro
     * cuando se está editando, si no una persona no podría guardarse sin
     * cambiar su carnet.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // Solo letras: incluye acentos, ñ y ü, más espacios, guiones y
        // apóstrofes habituales en nombres compuestos. Prohíbe números y
        // símbolos. Al estar vacío, Laravel omite las reglas no implícitas,
        // así que los campos opcionales sin llenar no fallan aquí.
        $soloLetras = '/^[\p{L}\s\'\-]+$/u';

        return [
            'carnet' => [
                'required', 'string',
                'regex:/^[0-9]{7,11}$/',
                Rule::unique('personas', 'carnet')->ignore($this->personaId)->whereNull('deleted_at'),
            ],
            'nombres' => ['required', 'string', 'min:2', 'max:100', "regex:$soloLetras"],
            // Ambos apellidos son opcionales por separado, pero al menos uno
            // debe estar presente: personas con solo apellido paterno, solo
            // materno, o los dos.
            'apellido_paterno' => ['required_without:apellido_materno', 'string', 'min:2', 'max:60', "regex:$soloLetras"],
            'apellido_materno' => ['required_without:apellido_paterno', 'string', 'min:2', 'max:60', "regex:$soloLetras"],
            'celular' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => [
                'nullable', 'email:rfc', 'max:150',
                Rule::unique('personas', 'correo')->ignore($this->personaId)->whereNull('deleted_at'),
            ],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
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
     * Nombres de campo en español para los mensajes de validación.
     *
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
     * Validación en tiempo real: cada vez que cambia un campo se valida solo
     * ese campo, para no llenar el formulario de errores prematuros.
     */
    public function updated(string $campo): void
    {
        if (! in_array($campo, self::CAMPOS, true)) {
            return;
        }

        $this->validateOnly($campo);

        // Los apellidos se validan juntos: al llenar uno se revalida el otro
        // para que el error de "al menos un apellido" desaparezca al momento.
        if ($campo === 'apellido_paterno' || $campo === 'apellido_materno') {
            $this->validateOnly($campo === 'apellido_paterno' ? 'apellido_materno' : 'apellido_paterno');
        }
    }

    /**
     * Al buscar hay que volver a la primera página: si estabas en la 3 y el
     * filtro deja 5 resultados, verías una página vacía.
     */
    public function updatedBuscar(): void
    {
        $this->resetPage();
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
        $this->autorizar('personas.crear');

        $this->limpiarFormulario();
        $this->dispatch('abrir-modal-persona');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('personas.editar');

        $persona = Persona::findOrFail($id);

        $this->personaId = $persona->id;
        $this->carnet = (string) $persona->carnet;
        $this->nombres = (string) $persona->nombres;
        $this->apellido_paterno = (string) $persona->apellido_paterno;
        $this->apellido_materno = (string) $persona->apellido_materno;
        $this->celular = (string) $persona->celular;
        $this->direccion = (string) $persona->direccion;
        $this->correo = (string) $persona->correo;
        $this->fecha_nacimiento = $persona->fecha_nacimiento?->format('Y-m-d') ?? '';

        $this->resetValidation();
        $this->dispatch('abrir-modal-persona');
    }

    public function guardar(): void
    {
        $this->autorizar($this->personaId !== null ? 'personas.editar' : 'personas.crear');

        $datos = $this->validate();

        // Los campos opcionales vacíos se guardan como NULL, no como cadena
        // vacía: así el índice único del correo no choca entre dos personas
        // que simplemente no tienen correo.
        foreach (['apellido_materno', 'celular', 'direccion', 'correo', 'fecha_nacimiento'] as $campo) {
            $datos[$campo] = $datos[$campo] === '' ? null : $datos[$campo];
        }

        if ($this->personaId !== null) {
            Persona::findOrFail($this->personaId)->update($datos);
            $mensaje = 'Persona actualizada correctamente.';
        } else {
            Persona::create($datos);
            $mensaje = 'Persona registrada correctamente.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-persona');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('personas.eliminar');

        $persona = Persona::findOrFail($id);

        $this->eliminarId = $persona->id;
        $this->eliminarNombre = $persona->nombre_completo;

        $this->dispatch('abrir-modal-eliminar');
    }

    public function eliminar(): void
    {
        $this->autorizar('personas.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $persona = Persona::findOrFail($this->eliminarId);

        // Ningún usuario puede quedarse sin su ficha: si la persona tiene
        // cuenta de acceso, el borrado rompería la relación 1 a 1.
        if ($persona->user()->exists()) {
            $this->dispatch('cerrar-modal-eliminar');
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se puede eliminar: esta persona tiene una cuenta de usuario vinculada.');

            return;
        }

        $persona->delete();

        $this->reset(['eliminarId', 'eliminarNombre']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Persona eliminada correctamente.');
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
        $this->reset([...self::CAMPOS, 'personaId']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $personas = Persona::query()
            ->with('user')
            ->buscar($this->buscar)
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable: sin esto, dos personas con el mismo apellido
            // pueden intercambiar posición entre páginas y aparecer repetidas.
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.personas.index', [
            'personas' => $personas,
            // Totales del encabezado: son del universo completo, no del filtro.
            'totalPersonas' => Persona::count(),
            'totalConCorreo' => Persona::whereNotNull('correo')->count(),
            'totalConCelular' => Persona::whereNotNull('celular')->count(),
            'cumpleaniosMes' => Persona::whereNotNull('fecha_nacimiento')
                ->whereMonth('fecha_nacimiento', now()->month)
                ->count(),
        ]);
    }
}
