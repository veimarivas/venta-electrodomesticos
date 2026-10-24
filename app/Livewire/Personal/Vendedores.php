<?php

namespace App\Livewire\Personal;

use App\Models\Trabajador;
use App\Models\User;
use App\Support\CuentaDeTrabajador;
use App\Support\DiasDeTrabajo;
use App\Support\EquipoDeVentas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Vendedores: el personal con su cuenta de acceso y sus días de trabajo.
 *
 * Tres cosas desde un mismo lugar:
 *  - crear la cuenta de quien todavía no tiene (usuario = inicial y apellidos,
 *    contraseña temporal = carnet, que se cambia al entrar);
 *  - reiniciar el acceso de quien lo olvidó (modal `ReiniciarAcceso`);
 *  - armar el calendario del mes: qué días le toca trabajar a cada uno, con
 *    atajos (lunes a sábado, como el mes anterior…). Con él, el historial de
 *    asistencia cuenta las faltas.
 */
class Vendedores extends Component
{
    #[Url(as: 'mes')]
    public string $mes = '';

    #[Url(as: 'q')]
    public string $buscar = '';

    // ---- Crear cuenta --------------------------------------------------------

    public ?int $cuentaTrabajadorId = null;

    public string $cuentaNombre = '';

    public string $cuentaUsuario = '';

    public string $cuentaRol = CuentaDeTrabajador::ROL_POR_DEFECTO;

    /** Ya creada: el modal enseña los datos para dárselos. */
    public bool $cuentaCreada = false;

    public string $cuentaTemporal = '';

    // ---- Días de trabajo -----------------------------------------------------

    public ?int $diasUserId = null;

    public string $diasNombre = '';

    /** @var list<string> Fechas Y-m-d marcadas en el calendario. */
    public array $fechas = [];

    /** @var list<string> Lo que daría «como el mes anterior». */
    public array $comoMesAnterior = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAny(['trabajadores.ver', 'asistencia.programar']) ?? false, 403);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }
    }

    private function inicio(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->mes)->startOfMonth();
    }

    public function mesAnterior(): void
    {
        $this->mes = $this->inicio()->subMonth()->format('Y-m');
        unset($this->filas);
    }

    public function mesSiguiente(): void
    {
        $this->mes = $this->inicio()->addMonth()->format('Y-m');
        unset($this->filas);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function filas(): array
    {
        $inicio = $this->inicio();

        return app(EquipoDeVentas::class)->listado($inicio->year, $inicio->month, $this->buscar);
    }

    #[On('acceso-reiniciado')]
    public function refrescar(): void
    {
        unset($this->filas);
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    #[Computed]
    public function roles(): \Illuminate\Support\Collection
    {
        return Role::query()->orderBy('name')->pluck('name');
    }

    // =======================================================================
    // Crear cuenta
    // =======================================================================

    public function confirmarCrearCuenta(int $trabajadorId): void
    {
        abort_unless(auth()->user()?->can('usuarios.crear') ?? false, 403);

        $trabajador = Trabajador::with('persona.user')->findOrFail($trabajadorId);

        if ($trabajador->persona->user !== null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Este trabajador ya tiene cuenta de usuario.');

            return;
        }

        $this->cuentaTrabajadorId = $trabajador->id;
        $this->cuentaNombre = $trabajador->persona->nombre_completo;
        $this->cuentaUsuario = app(CuentaDeTrabajador::class)->usuario($trabajador->persona);
        $this->cuentaRol = CuentaDeTrabajador::ROL_POR_DEFECTO;
        $this->cuentaCreada = false;
        $this->cuentaTemporal = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-crear-cuenta-vendedor');
    }

    public function crearCuenta(): void
    {
        abort_unless(auth()->user()?->can('usuarios.crear') ?? false, 403);

        $this->validate(
            ['cuentaRol' => ['required', 'string', Rule::exists('roles', 'name')]],
            ['cuentaRol.required' => 'Elige el rol de la cuenta.'],
        );

        $trabajador = Trabajador::with('persona.user')->find($this->cuentaTrabajadorId);

        // Se vuelve a mirar: otro pudo crearla mientras el modal estaba abierto.
        if ($trabajador === null || $trabajador->persona->user !== null || ! $trabajador->esta_activo) {
            $this->dispatch('cerrar-modal-crear-cuenta-vendedor');
            $this->dispatch('toast', tipo: 'error', mensaje: 'Ya no se puede crear la cuenta de este trabajador.');

            return;
        }

        $servicio = app(CuentaDeTrabajador::class);
        $usuario = $servicio->crear($trabajador->persona, $this->cuentaRol);

        $this->cuentaUsuario = $usuario->name;
        $this->cuentaTemporal = $servicio->passwordDe($trabajador->persona);
        $this->cuentaCreada = true;
        unset($this->filas);

        $this->dispatch('toast', tipo: 'success', mensaje: "Cuenta «{$usuario->name}» creada.");
    }

    // =======================================================================
    // Días de trabajo
    // =======================================================================

    public function abrirDias(int $userId): void
    {
        abort_unless(auth()->user()?->can('asistencia.programar') ?? false, 403);

        $usuario = User::with('persona')->findOrFail($userId);
        $inicio = $this->inicio();
        $servicio = app(DiasDeTrabajo::class);

        $this->diasUserId = $usuario->id;
        $this->diasNombre = $usuario->persona?->nombre_completo ?? $usuario->name;
        $this->fechas = $servicio->delMes($usuario->id, $inicio->year, $inicio->month);
        $this->comoMesAnterior = $servicio->comoElMesAnterior($usuario->id, $inicio->year, $inicio->month);
        $this->resetValidation();

        $this->dispatch('abrir-modal-dias-de-trabajo');
    }

    public function alternarDia(string $fecha): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return;
        }

        $this->fechas = in_array($fecha, $this->fechas, true)
            ? array_values(array_diff($this->fechas, [$fecha]))
            : [...$this->fechas, $fecha];
    }

    /** Atajos: «lunes a sábado», «lunes a viernes», «todo», «ninguno», «mes anterior». */
    public function aplicarPatron(string $patron): void
    {
        $inicio = $this->inicio();
        $servicio = app(DiasDeTrabajo::class);

        $this->fechas = match ($patron) {
            'lunes-sabado' => $servicio->patron($inicio->year, $inicio->month, [1, 2, 3, 4, 5, 6]),
            'lunes-viernes' => $servicio->patron($inicio->year, $inicio->month, [1, 2, 3, 4, 5]),
            'todos' => $servicio->patron($inicio->year, $inicio->month, [1, 2, 3, 4, 5, 6, 7]),
            'mes-anterior' => $this->comoMesAnterior,
            default => [],
        };
    }

    public function guardarDias(): void
    {
        abort_unless(auth()->user()?->can('asistencia.programar') ?? false, 403);

        $inicio = $this->inicio();

        try {
            $guardadas = app(DiasDeTrabajo::class)->guardar(
                User::findOrFail($this->diasUserId),
                $inicio->year,
                $inicio->month,
                $this->fechas,
                auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->addError('fechas', $e->getMessage());

            return;
        }

        unset($this->filas);
        $this->dispatch('cerrar-modal-dias-de-trabajo');
        $this->dispatch('toast', tipo: 'success', mensaje: count($guardadas) === 1
            ? "1 día de trabajo para {$this->diasNombre}."
            : count($guardadas)." días de trabajo para {$this->diasNombre}.");
    }

    /**
     * Las semanas del mes para pintar el calendario (lunes primero). Las
     * celdas fuera del mes van en null.
     *
     * @return list<list<?Carbon>>
     */
    public function semanas(): array
    {
        $inicio = $this->inicio();
        $dia = $inicio->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $inicio->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $semanas = [];

        while ($dia <= $fin) {
            $semana = [];

            for ($i = 0; $i < 7; $i++) {
                $semana[] = $dia->month === $inicio->month ? $dia->copy() : null;
                $dia->addDay();
            }

            $semanas[] = $semana;
        }

        return $semanas;
    }

    public function render(): View
    {
        return view('livewire.personal.vendedores', [
            'inicio' => $this->inicio(),
            'esMesActual' => $this->mes === now()->format('Y-m'),
        ]);
    }
}
