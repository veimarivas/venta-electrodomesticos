<?php

namespace App\Livewire\Usuarios;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    use WithPagination;

    /** Livewire usa la paginación de Tailwind si no se le indica el tema. */
    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    public string $ordenarPor = 'name';

    public string $direccionOrden = 'asc';

    /** activos | inactivos | todos */
    public string $filtroEstado = 'todos';

    /** Filtra por rol; cadena vacía = todos. */
    public string $filtroRol = '';

    /** Id del usuario en edición; null significa "cuenta nueva". */
    public ?int $usuarioId = null;

    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    // ---- Campos del formulario -------------------------------------------

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Roles marcados. Se guardan como cadenas por el binding de los checkbox. */
    public array $roles = [];

    /** Persona con la que se vincula la cuenta (relación 1 a 1). */
    public string $persona_id = '';

    public bool $is_active = true;

    private const CAMPOS = [
        'name', 'email', 'phone', 'password', 'password_confirmation',
        'roles', 'persona_id', 'is_active',
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
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:150',
                Rule::unique('users', 'email')->ignore($this->usuarioId),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],

            // Al crear la contraseña es obligatoria; al editar solo se valida
            // si se escribió algo, porque dejarla vacía significa "no cambiar".
            'password' => [
                $this->usuarioId === null ? 'required' : 'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],

            'persona_id' => [
                'required', 'integer',
                Rule::exists('personas', 'id')->whereNull('deleted_at'),
                // La relación es 1 a 1: una persona no puede tener dos cuentas.
                Rule::unique('users', 'persona_id')->ignore($this->usuarioId),
            ],

            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con este correo.',
            'phone.regex' => 'El teléfono debe tener 8 números.',
            'password.required' => 'Debes definir una contraseña para la cuenta nueva.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'roles.required' => 'Debes asignar al menos un rol.',
            'roles.min' => 'Debes asignar al menos un rol.',
            'persona_id.required' => 'Debes elegir la persona de esta cuenta.',
            'persona_id.unique' => 'Esa persona ya tiene una cuenta vinculada.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'password' => 'contraseña',
            'roles' => 'roles',
            'persona_id' => 'persona',
        ];
    }

    public function updated(string $campo): void
    {
        // 'roles.0' y similares deben validar la regla del array completo.
        $base = str_contains($campo, '.') ? explode('.', $campo)[0] : $campo;

        if (! in_array($base, self::CAMPOS, true)) {
            return;
        }

        $this->validateOnly($base);

        // Confirmar la contraseña afecta a la regla 'confirmed' del otro campo.
        if ($base === 'password_confirmation') {
            $this->validateOnly('password');
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

    public function updatedFiltroRol(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function formularioValido(): bool
    {
        return Validator::make(
            $this->only(['name', 'email', 'phone', 'password', 'password_confirmation', 'roles', 'persona_id', 'is_active']),
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    /** Roles disponibles para asignar. */
    #[Computed]
    public function rolesDisponibles(): Collection
    {
        return Role::withCount('permissions')->orderBy('name')->get();
    }

    /**
     * Personas sin cuenta, más la ya vinculada al usuario en edición.
     */
    #[Computed]
    public function personasVinculables(): Collection
    {
        return Persona::query()
            ->where(function ($q) {
                $q->whereDoesntHave('user');

                if ($this->usuarioId !== null) {
                    $q->orWhereHas('user', fn ($u) => $u->whereKey($this->usuarioId));
                }
            })
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->limit(200)
            ->get();
    }

    // =======================================================================
    // Alta y edición
    // =======================================================================

    public function abrirCrear(): void
    {
        $this->autorizar('usuarios.crear');

        $this->limpiarFormulario();
        $this->dispatch('abrir-modal-usuario');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('usuarios.editar');

        $usuario = User::with(['roles', 'persona'])->findOrFail($id);

        $this->usuarioId = $usuario->id;
        $this->name = (string) $usuario->name;
        $this->email = (string) $usuario->email;
        $this->phone = (string) $usuario->phone;
        $this->is_active = (bool) $usuario->is_active;
        $this->roles = $usuario->roles->pluck('name')->all();
        $this->persona_id = (string) ($usuario->persona?->id ?? '');

        // Nunca se precarga la contraseña: vacía significa "no cambiarla".
        $this->password = '';
        $this->password_confirmation = '';

        $this->resetValidation();
        $this->dispatch('abrir-modal-usuario');
    }

    public function guardar(): void
    {
        $this->autorizar($this->usuarioId !== null ? 'usuarios.editar' : 'usuarios.crear');

        $datos = $this->validate();

        // Nadie puede quitarse a sí mismo el rol de administrador ni
        // desactivarse: se quedaría fuera del sistema sin poder volver.
        if ($this->usuarioId === auth()->id()) {
            if (auth()->user()->hasRole('admin') && ! in_array('admin', $datos['roles'], true)) {
                $this->addError('roles', 'No puedes quitarte a ti mismo el rol de administrador.');

                return;
            }

            if (! $datos['is_active']) {
                $this->addError('is_active', 'No puedes desactivar tu propia cuenta.');

                return;
            }
        }

        $usuario = $this->usuarioId !== null
            ? User::findOrFail($this->usuarioId)
            : new User;

        $usuario->fill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'phone' => $datos['phone'] !== '' ? $datos['phone'] : null,
            'is_active' => $datos['is_active'],
            'persona_id' => (int) $datos['persona_id'],
        ]);

        // Contraseña vacía al editar = se conserva la actual.
        if ($datos['password'] !== '') {
            $usuario->password = $datos['password'];
        }

        $usuario->save();
        $usuario->syncRoles($datos['roles']);

        $mensaje = $this->usuarioId !== null
            ? 'Usuario actualizado correctamente.'
            : 'Usuario creado correctamente.';

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-usuario');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    /**
     * Activa o desactiva la cuenta desde el listado, sin abrir el modal.
     */
    public function alternarEstado(int $id): void
    {
        $this->autorizar('usuarios.editar');

        $usuario = User::findOrFail($id);

        if ($usuario->id === auth()->id()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No puedes desactivar tu propia cuenta.');

            return;
        }

        $usuario->update(['is_active' => ! $usuario->is_active]);

        $this->dispatch('toast', tipo: 'success', mensaje: $usuario->is_active
            ? "La cuenta de {$usuario->name} fue activada."
            : "La cuenta de {$usuario->name} fue desactivada.");
    }

    // =======================================================================
    // Eliminación
    // =======================================================================

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('usuarios.eliminar');

        $usuario = User::findOrFail($id);

        $this->eliminarId = $usuario->id;
        $this->eliminarNombre = $usuario->name;

        $this->dispatch('abrir-modal-eliminar-usuario');
    }

    public function eliminar(): void
    {
        $this->autorizar('usuarios.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        if ($this->eliminarId === auth()->id()) {
            $this->dispatch('cerrar-modal-eliminar-usuario');
            $this->dispatch('toast', tipo: 'error', mensaje: 'No puedes eliminar tu propia cuenta.');

            return;
        }

        $usuario = User::findOrFail($this->eliminarId);

        // Si es el último administrador, borrarlo dejaría el sistema sin nadie
        // que pueda gestionar usuarios ni permisos.
        if ($usuario->hasRole('admin') && User::role('admin')->count() === 1) {
            $this->dispatch('cerrar-modal-eliminar-usuario');
            $this->dispatch('toast', tipo: 'error', mensaje: 'No puedes eliminar al único administrador del sistema.');

            return;
        }

        // La persona vinculada no se borra: la relación vive en users y, al
        // eliminar la cuenta, la ficha queda libre sin más cambios.
        $usuario->delete();

        $this->reset(['eliminarId', 'eliminarNombre']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-usuario');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Usuario eliminado correctamente.');
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
        $this->reset([...self::CAMPOS, 'usuarioId']);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $usuarios = User::query()
            ->with(['roles', 'persona'])
            ->when($this->buscar !== '', function ($q) {
                $termino = trim($this->buscar);
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$termino}%")
                    ->orWhere('email', 'like', "%{$termino}%"));
            })
            ->when($this->filtroEstado === 'activos', fn ($q) => $q->where('is_active', true))
            ->when($this->filtroEstado === 'inactivos', fn ($q) => $q->where('is_active', false))
            ->when($this->filtroRol !== '', fn ($q) => $q->role($this->filtroRol))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'totalUsuarios' => User::count(),
            'totalActivos' => User::where('is_active', true)->count(),
            'totalRoles' => Role::count(),
            'personasSinCuenta' => Persona::doesntHave('user')->count(),
        ]);
    }
}
