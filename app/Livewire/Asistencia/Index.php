<?php

namespace App\Livewire\Asistencia;

use App\Models\Asistencia;
use App\Models\Tienda;
use App\Models\User;
use App\Support\RegistroDeAsistencia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Historial de asistencia del mes, por trabajador: días, horas, atrasos y
 * salidas sin marcar, con el detalle de cada día y cada turno. Se puede poner
 * la salida que alguien olvidó (queda anotado quién y por qué) y descargar en
 * PDF o CSV.
 */
class Index extends Component
{
    #[Url(as: 'mes')]
    public string $mes = '';

    #[Url(as: 'trabajador')]
    public ?int $userId = null;

    #[Url(as: 'tienda')]
    public ?int $tiendaId = null;

    /** Trabajador con el detalle abierto. */
    public ?int $abierto = null;

    // ---- Corregir salida -----------------------------------------------------

    public ?int $corregirId = null;

    public string $horaSalida = '';

    public string $motivo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('asistencia.ver') ?? false, 403);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }
    }

    public function mesAnterior(): void
    {
        $this->mes = $this->inicio()->subMonth()->format('Y-m');
        $this->limpiar();
    }

    public function mesSiguiente(): void
    {
        $this->mes = $this->inicio()->addMonth()->format('Y-m');
        $this->limpiar();
    }

    public function updatedMes(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }

        $this->limpiar();
    }

    public function updatedUserId(): void
    {
        $this->limpiar();
    }

    public function updatedTiendaId(): void
    {
        $this->limpiar();
    }

    private function limpiar(): void
    {
        $this->abierto = null;
        unset($this->historial);
    }

    public function alternar(int $userId): void
    {
        $this->abierto = $this->abierto === $userId ? null : $userId;
    }

    private function inicio(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->mes)->startOfMonth();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function historial(): array
    {
        $inicio = $this->inicio();

        return app(RegistroDeAsistencia::class)->historial(
            (int) $inicio->year,
            (int) $inicio->month,
            $this->userId ?: null,
            $this->tiendaId ?: null,
        );
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function trabajadores(): Collection
    {
        return User::query()
            ->where(fn ($q) => $q->permission('asistencia.marcar')
                ->orWhereIn('id', Asistencia::query()->select('user_id')->distinct()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return Collection<int, Tienda> */
    #[Computed]
    public function tiendas(): Collection
    {
        return Tienda::withTrashed()->orderBy('nombre')->get(['id', 'nombre']);
    }

    public function abrirCorreccion(int $id): void
    {
        $asistencia = Asistencia::findOrFail($id);

        $this->corregirId = $asistencia->id;
        $this->horaSalida = $asistencia->salida_en?->format('H:i') ?? '';
        $this->motivo = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-corregir-asistencia');
    }

    public function corregir(): void
    {
        abort_unless(auth()->user()?->can('asistencia.ver') ?? false, 403);

        $this->validate([
            'horaSalida' => ['required', 'date_format:H:i'],
            'motivo' => ['required', 'string', 'min:4', 'max:255'],
        ], [
            'horaSalida.required' => 'Indica a qué hora salió.',
            'motivo.required' => 'Indica por qué se corrige (ej. «olvidó marcar, confirmó el supervisor»).',
        ]);

        $asistencia = Asistencia::findOrFail($this->corregirId);

        try {
            app(RegistroDeAsistencia::class)->corregirSalida(
                $asistencia,
                $asistencia->fecha->copy()->setTimeFromTimeString($this->horaSalida),
                auth()->user(),
                $this->motivo,
            );
        } catch (RuntimeException $e) {
            $this->addError('horaSalida', $e->getMessage());

            return;
        }

        $this->corregirId = null;
        unset($this->historial);

        $this->dispatch('cerrar-modal-corregir-asistencia');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Salida corregida.');
    }

    public function render(): View
    {
        $filas = collect($this->historial);
        $corregir = $this->corregirId ? Asistencia::with(['user:id,name', 'tienda:id,nombre'])->find($this->corregirId) : null;

        return view('livewire.asistencia.index', [
            'filas' => $filas,
            'inicio' => $this->inicio(),
            'esMesActual' => $this->mes === now()->format('Y-m'),
            'corregir' => $corregir,
            'totales' => [
                'personas' => $filas->count(),
                'dias' => (int) $filas->sum('dias'),
                'minutos' => (int) $filas->sum('minutos'),
                'atrasos' => (int) $filas->sum('atrasos'),
                'minutos_atraso' => (int) $filas->sum('minutos_atraso'),
                'sin_salida' => (int) $filas->sum('sin_salida'),
            ],
        ]);
    }
}
