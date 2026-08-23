<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Cuentas de acceso al sistema.
 *
 * Mismas reglas y mismas guardas que el panel (`App\Livewire\Usuarios\Index`).
 * Las dos que importan: **nadie puede desactivar ni borrar su propia cuenta**
 * —se quedaría fuera del sistema en el acto— y **no se puede borrar al último
 * administrador**, porque dejaría el sistema sin nadie capaz de gestionar
 * usuarios ni permisos.
 *
 * La contraseña **nunca viaja de vuelta**: se manda al crear o al cambiarla, y
 * la respuesta solo confirma que la cuenta existe.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:activos,inactivos,todos'],
            'rol' => ['nullable', 'string', 'max:50'],
        ]);

        $estado = $datos['estado'] ?? 'todos';
        $termino = trim($datos['buscar'] ?? '');

        $usuarios = User::query()
            ->with(['persona', 'roles'])
            ->when($estado === 'activos', fn ($q) => $q->where('is_active', true))
            ->when($estado === 'inactivos', fn ($q) => $q->where('is_active', false))
            ->when(isset($datos['rol']), fn ($q) => $q->role($datos['rol']))
            ->when($termino !== '', fn ($q) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$termino}%")
                    ->orWhere('email', 'like', "%{$termino}%")
            ))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $usuarios->map(fn (User $u): array => $this->aJson($u))->values(),
        ]);
    }

    /**
     * Personas que todavía no tienen cuenta.
     *
     * La relación es 1 a 1: ofrecer una persona que ya tiene cuenta llevaría a
     * un error de unicidad después de haber llenado el formulario entero.
     */
    public function personasVinculables(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'termino' => ['nullable', 'string', 'max:100'],
        ]);

        $personas = Persona::query()
            ->buscar($datos['termino'] ?? null)
            ->whereDoesntHave('user')
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $personas->map(fn (Persona $p): array => [
                'id' => $p->id,
                'nombre_completo' => $p->nombre_completo,
                'carnet' => $p->carnet,
                'iniciales' => $p->iniciales,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, null);

        $usuario = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'phone' => $datos['phone'],
            'persona_id' => $datos['persona_id'],
            'password' => Hash::make($datos['password']),
            'is_active' => $datos['is_active'],
        ]);

        $usuario->syncRoles($datos['roles']);

        return response()->json(['data' => $this->aJson($usuario->fresh()->load('persona', 'roles'))], 201);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $datos = $this->validar($request, $usuario);

        $cambios = [
            'name' => $datos['name'],
            'email' => $datos['email'],
            'phone' => $datos['phone'],
            'persona_id' => $datos['persona_id'],
        ];

        // Vacía significa «no cambiarla», no «dejarla en blanco».
        if (($datos['password'] ?? '') !== '') {
            $cambios['password'] = Hash::make($datos['password']);
        }

        // Quitarse el propio último rol de administrador dejaría al sistema sin
        // quien lo administre, igual que borrar la cuenta.
        $this->comprobarQueQuedaUnAdmin($usuario, $datos['roles']);

        $usuario->update($cambios);
        $usuario->syncRoles($datos['roles']);

        return response()->json(['data' => $this->aJson($usuario->fresh()->load('persona', 'roles'))]);
    }

    /**
     * Activa o desactiva la cuenta.
     *
     * Es la vía normal para cerrarle el paso a alguien sin borrar nada: sus
     * ventas y compras siguen apuntando a la cuenta.
     */
    public function alternarEstado(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->is($request->user())) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes desactivar tu propia cuenta.',
            ]);
        }

        $usuario->update(['is_active' => ! $usuario->is_active]);

        return response()->json(['data' => $this->aJson($usuario->fresh()->load('persona', 'roles'))]);
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->is($request->user())) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        if ($usuario->hasRole('admin') && User::role('admin')->count() === 1) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes eliminar al único administrador del sistema.',
            ]);
        }

        // La persona vinculada NO se borra: la relación vive en `users` y al
        // eliminar la cuenta su ficha queda libre, sin más cambios.
        $usuario->delete();

        return response()->json(['mensaje' => 'Cuenta eliminada.']);
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function comprobarQueQuedaUnAdmin(User $usuario, array $roles): void
    {
        $perdiaElRol = $usuario->hasRole('admin') && ! in_array('admin', $roles, true);

        if ($perdiaElRol && User::role('admin')->count() === 1) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes quitarle el rol al único administrador del sistema.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?User $usuario): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:150',
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            // Al crear es obligatoria; al editar solo se valida si se escribió
            // algo, porque dejarla vacía significa «no cambiar».
            'password' => [
                $usuario === null ? 'required' : 'nullable',
                Password::min(8)->letters()->numbers(),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],
            'persona_id' => [
                'required', 'integer',
                Rule::exists('personas', 'id')->whereNull('deleted_at'),
                // La relación es 1 a 1: una persona no puede tener dos cuentas.
                Rule::unique('users', 'persona_id')->ignore($usuario?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'El teléfono debe tener 8 números.',
            'persona_id.unique' => 'Esa persona ya tiene una cuenta de acceso.',
            'roles.required' => 'La cuenta necesita al menos un rol.',
        ]);

        $telefono = trim((string) ($datos['phone'] ?? ''));

        return [
            ...$datos,
            'phone' => $telefono === '' ? null : $telefono,
            'is_active' => $datos['is_active'] ?? $usuario?->is_active ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aJson(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            // El nombre con el que entra, no el de pila.
            'usuario' => $usuario->name,
            'correo' => $usuario->email,
            'telefono' => $usuario->phone,
            'activa' => (bool) $usuario->is_active,
            'roles' => $usuario->getRoleNames()->all(),
            'ultimo_acceso' => $usuario->last_login_at?->toIso8601String(),
            'persona' => $usuario->persona === null ? null : [
                'id' => $usuario->persona->id,
                'nombre_completo' => $usuario->persona->nombre_completo,
                'carnet' => $usuario->persona->carnet,
                'iniciales' => $usuario->persona->iniciales,
            ],
        ];
    }
}
