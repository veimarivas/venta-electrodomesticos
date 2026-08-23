<?php

namespace App\Livewire\Roles;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Index extends Component
{
    /**
     * El rol de administrador no se edita ni se borra: AppServiceProvider le
     * concede todo mediante Gate::before, así que su matriz de permisos es
     * informativa. Tocarlo solo serviría para bloquear el sistema.
     */
    public const ROL_PROTEGIDO = 'admin';

    /** Id del rol en edición; null significa "rol nuevo". */
    public ?int $rolId = null;

    public string $nombre = '';

    /** Rol cuya matriz de permisos se está editando. */
    public ?int $permisosRolId = null;

    public string $permisosRolNombre = '';

    /** Permisos marcados en la matriz. */
    public array $permisosSeleccionados = [];

    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    public int $eliminarUsuarios = 0;

    // =======================================================================
    // Validación del rol
    // =======================================================================

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'min:3', 'max:50',
                // Un nombre de rol se usa como identificador en el código
                // (hasRole('supervisor')), así que se limita a algo simple.
                'regex:/^[a-zA-Z0-9\s\-_]+$/',
                Rule::unique('roles', 'name')->ignore($this->rolId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras, números, guiones y guiones bajos.',
            'nombre.unique' => 'Ya existe un rol con este nombre.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['nombre' => 'nombre del rol'];
    }

    public function updated(string $campo): void
    {
        if ($campo === 'nombre') {
            $this->validateOnly('nombre');
        }
    }

    #[Computed]
    public function formularioValido(): bool
    {
        return Validator::make(
            ['nombre' => $this->nombre],
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    // =======================================================================
    // Datos para la vista
    // =======================================================================

    /**
     * Permisos agrupados por módulo: 'ventas.crear' cae bajo 'ventas'.
     * De ahí sale la matriz de la pantalla.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    #[Computed]
    public function permisosPorModulo(): Collection
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permiso) => Str::before($permiso->name, '.'));
    }

    /**
     * Cuántos permisos hay en total, para el contador de la matriz.
     */
    #[Computed]
    public function totalPermisos(): int
    {
        return Permission::count();
    }

    // =======================================================================
    // Alta y edición del rol
    // =======================================================================

    public function abrirCrear(): void
    {
        $this->autorizar('roles.crear');

        $this->reset(['rolId', 'nombre']);
        $this->resetValidation();

        $this->dispatch('abrir-modal-rol');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('roles.editar');

        $rol = Role::findOrFail($id);

        if ($this->esProtegido($rol)) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'El rol de administrador no se puede renombrar.');

            return;
        }

        $this->rolId = $rol->id;
        $this->nombre = $rol->name;

        $this->resetValidation();
        $this->dispatch('abrir-modal-rol');
    }

    public function guardar(): void
    {
        $this->autorizar($this->rolId !== null ? 'roles.editar' : 'roles.crear');

        $datos = $this->validate();

        if ($this->rolId !== null) {
            $rol = Role::findOrFail($this->rolId);

            if ($this->esProtegido($rol)) {
                $this->dispatch('toast', tipo: 'error', mensaje: 'El rol de administrador no se puede renombrar.');

                return;
            }

            $rol->update(['name' => $datos['nombre']]);
            $mensaje = 'Rol actualizado correctamente.';
        } else {
            Role::create(['name' => $datos['nombre'], 'guard_name' => 'web']);
            $mensaje = 'Rol creado correctamente.';
        }

        $this->olvidarCache();

        $this->reset(['rolId', 'nombre']);
        $this->dispatch('cerrar-modal-rol');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // =======================================================================
    // Matriz de permisos
    // =======================================================================

    public function abrirPermisos(int $id): void
    {
        $this->autorizar('roles.editar');

        $rol = Role::with('permissions')->findOrFail($id);

        $this->permisosRolId = $rol->id;
        $this->permisosRolNombre = $rol->name;
        $this->permisosSeleccionados = $rol->permissions->pluck('name')->all();

        $this->dispatch('abrir-modal-permisos-rol');
    }

    /** Marca o desmarca todos los permisos de un módulo de una vez. */
    public function alternarModulo(string $modulo): void
    {
        $delModulo = $this->permisosPorModulo[$modulo]->pluck('name')->all();
        $todosMarcados = empty(array_diff($delModulo, $this->permisosSeleccionados));

        $this->permisosSeleccionados = $todosMarcados
            ? array_values(array_diff($this->permisosSeleccionados, $delModulo))
            : array_values(array_unique([...$this->permisosSeleccionados, ...$delModulo]));
    }

    public function marcarTodos(): void
    {
        $this->permisosSeleccionados = Permission::pluck('name')->all();
    }

    public function desmarcarTodos(): void
    {
        $this->permisosSeleccionados = [];
    }

    public function guardarPermisos(): void
    {
        $this->autorizar('roles.editar');

        if ($this->permisosRolId === null) {
            return;
        }

        $rol = Role::findOrFail($this->permisosRolId);

        if ($this->esProtegido($rol)) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'El rol de administrador ya tiene acceso total.');

            return;
        }

        // Solo se sincronizan permisos que existen de verdad: los nombres
        // llegan del navegador y no se pueden dar por buenos.
        $validos = Permission::whereIn('name', $this->permisosSeleccionados)->pluck('name')->all();

        $rol->syncPermissions($validos);
        $this->olvidarCache();

        $this->reset(['permisosRolId', 'permisosRolNombre', 'permisosSeleccionados']);

        $this->dispatch('cerrar-modal-permisos-rol');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Permisos actualizados correctamente.');
    }

    // =======================================================================
    // Eliminación
    // =======================================================================

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('roles.eliminar');

        $rol = Role::withCount('users')->findOrFail($id);

        if ($this->esProtegido($rol)) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'El rol de administrador no se puede eliminar.');

            return;
        }

        $this->eliminarId = $rol->id;
        $this->eliminarNombre = $rol->name;
        $this->eliminarUsuarios = $rol->users_count;

        $this->dispatch('abrir-modal-eliminar-rol');
    }

    public function eliminar(): void
    {
        $this->autorizar('roles.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $rol = Role::withCount('users')->findOrFail($this->eliminarId);

        if ($this->esProtegido($rol)) {
            $this->dispatch('cerrar-modal-eliminar-rol');
            $this->dispatch('toast', tipo: 'error', mensaje: 'El rol de administrador no se puede eliminar.');

            return;
        }

        // Borrar un rol en uso dejaría a esos usuarios sin ningún permiso,
        // sin que nadie se entere hasta que intenten entrar a algo.
        if ($rol->users_count > 0) {
            $this->dispatch('cerrar-modal-eliminar-rol');
            $this->dispatch('toast', tipo: 'error', mensaje: "No se puede eliminar: {$rol->users_count} usuario(s) tienen este rol.");

            return;
        }

        $rol->delete();
        $this->olvidarCache();

        $this->reset(['eliminarId', 'eliminarNombre', 'eliminarUsuarios']);

        $this->dispatch('cerrar-modal-eliminar-rol');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Rol eliminado correctamente.');
    }

    // =======================================================================

    private function esProtegido(Role $rol): bool
    {
        return $rol->name === self::ROL_PROTEGIDO;
    }

    /**
     * spatie cachea los permisos: sin esto, los cambios no surten efecto
     * hasta que expire la caché.
     */
    private function olvidarCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }
}
