<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles y sus permisos.
 *
 * Mismas reglas y guardas que el panel (`App\Livewire\Roles\Index`).
 *
 * **El rol `admin` está protegido**: no se edita, no se le tocan los permisos y
 * no se borra. Tiene acceso total por `Gate::before()` en `AppServiceProvider`,
 * así que su lista de permisos es irrelevante y dejar que se edite solo
 * confundiría; borrarlo dejaría el sistema sin quien lo administre.
 */
class RolController extends Controller
{
    private const ROL_PROTEGIDO = 'admin';

    public function index(): JsonResponse
    {
        $roles = $this->conConteos()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $rol): array => $this->aJson($rol))->values(),
        ]);
    }

    /**
     * Todos los permisos, agrupados por módulo.
     *
     * Es lo que necesita la app para pintar la matriz: en una lista plana de
     * casi cien permisos no se encuentra nada.
     */
    public function permisos(): JsonResponse
    {
        $porModulo = Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permiso) => Str::before($permiso->name, '.'));

        return response()->json([
            'data' => $porModulo->map(fn ($permisos, $modulo): array => [
                'modulo' => $modulo,
                'permisos' => $permisos->map(fn (Permission $p): array => [
                    'id' => $p->id,
                    'nombre' => $p->name,
                    // La acción sola («ver», «crear»): el módulo ya va aparte.
                    'accion' => Str::after($p->name, '.'),
                ])->values(),
            ])->values(),
        ]);
    }

    /** Los permisos que tiene un rol concreto, para marcar la matriz. */
    public function permisosDelRol(int $rol): JsonResponse
    {
        $rol = $this->buscar($rol);

        return response()->json([
            'data' => $rol->permissions()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, null);

        $rol = Role::create(['name' => $datos['nombre'], 'guard_name' => 'web']);

        return response()->json(['data' => $this->aJson($this->fichaConConteos($rol->id))], 201);
    }

    public function update(Request $request, int $rol): JsonResponse
    {
        $rol = $this->buscar($rol);

        $this->negarSiEsProtegido($rol, 'El rol de administrador no se puede editar.');

        $datos = $this->validar($request, $rol);

        $rol->update(['name' => $datos['nombre']]);
        $this->olvidarCache();

        return response()->json(['data' => $this->aJson($this->fichaConConteos($rol->id))]);
    }

    /**
     * Reemplaza los permisos del rol por los que se manden.
     */
    public function sincronizarPermisos(Request $request, int $rol): JsonResponse
    {
        $rol = $this->buscar($rol);

        $this->negarSiEsProtegido($rol, 'El rol de administrador ya tiene acceso total.');

        $datos = $request->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string'],
        ]);

        // Solo se sincronizan permisos que existen de verdad: los nombres
        // llegan del cliente y no se pueden dar por buenos.
        $validos = Permission::whereIn('name', $datos['permisos'])->pluck('name')->all();

        $rol->syncPermissions($validos);
        $this->olvidarCache();

        return response()->json([
            'mensaje' => 'Permisos actualizados.',
            'data' => $this->aJson($this->fichaConConteos($rol->id)),
        ]);
    }

    public function destroy(int $rol): JsonResponse
    {
        $rol = $this->buscar($rol);

        $this->negarSiEsProtegido($rol, 'El rol de administrador no se puede eliminar.');

        $rol->loadCount('users');

        // Borrar un rol en uso dejaría a esos usuarios sin ningún permiso, sin
        // que nadie se entere hasta que intenten entrar a algo.
        if ($rol->users_count > 0) {
            throw ValidationException::withMessages([
                'rol' => "No se puede eliminar: {$rol->users_count} usuario(s) lo tienen asignado.",
            ]);
        }

        $rol->delete();
        $this->olvidarCache();

        return response()->json(['mensaje' => 'Rol eliminado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Role $rol): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'min:3', 'max:50',
                // El nombre se usa como identificador en el código
                // (`hasRole('supervisor')`), así que se limita a algo simple.
                'regex:/^[a-zA-Z0-9\s\-_]+$/',
                Rule::unique('roles', 'name')->ignore($rol?->id),
            ],
        ], [
            'nombre.regex' => 'El nombre solo puede contener letras, números, espacios, guiones y guiones bajos.',
            'nombre.unique' => 'Ya existe un rol con este nombre.',
        ]);
    }

    /**
     * Busca el rol por id sin pasar por el route model binding.
     *
     * El binding de spatie resuelve el rol con el guard por DEFECTO, que en la
     * API bajo Sanctum es `sanctum`; los roles se crean con guard `web` y la
     * petición muere con «There is no role with ID X for guard sanctum». Una
     * consulta normal no mira el guard.
     */
    private function buscar(int $id): Role
    {
        return Role::query()->whereKey($id)->firstOrFail();
    }

    private function negarSiEsProtegido(Role $rol, string $mensaje): void
    {
        if ($rol->name === self::ROL_PROTEGIDO) {
            throw ValidationException::withMessages(['rol' => $mensaje]);
        }
    }

    /**
     * Consulta con los dos conteos que necesita [aJson].
     *
     * Los usuarios se cuentan con una subconsulta sobre la tabla pivote y no
     * con `withCount('users')`. La relación `users()` de spatie resuelve el
     * modelo a partir del `guard_name` del rol, y al construir un `withCount`
     * Eloquent la pide sobre una instancia **sin atributos**: el guard sale
     * nulo, el morph se queda sin clase y la petición revienta con un 500.
     * Contando por la pivote no depende de eso.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Role>
     */
    private function conConteos()
    {
        return Role::query()
            ->withCount('permissions')

            ->addSelect(['users_count' => DB::table('model_has_roles')
                ->selectRaw('count(*)')
                ->whereColumn('model_has_roles.role_id', 'roles.id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass()),
            ]);
    }

    private function fichaConConteos(int $id): Role
    {
        return $this->conConteos()->findOrFail($id);
    }

    /**
     * spatie cachea los permisos: sin esto, los cambios no surten efecto hasta
     * que expire la caché.
     */
    private function olvidarCache(): void
    {
        Artisan::call('permission:cache-reset');
    }

    /**
     * @return array<string, mixed>
     */
    private function aJson(Role $rol): array
    {
        return [
            'id' => $rol->id,
            'nombre' => $rol->name,
            'usuarios' => (int) $rol->users_count,
            'permisos' => (int) $rol->permissions_count,
            // El de administrador no se toca: la app oculta sus botones en vez
            // de dejar que el servidor los rechace uno a uno.
            'protegido' => $rol->name === self::ROL_PROTEGIDO,
        ];
    }
}
