<?php

namespace App\Livewire\Entregas;

use App\Models\Entrega;
use App\Models\User;
use App\Support\ProgramacionDeEntregas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * El tablero de entregas: qué sale hoy, qué está atrasado, qué anda en la calle.
 *
 * Arranca por lo abierto, no por lo entregado: una entrega hecha ya no da
 * trabajo, y el listado existe para contestar qué falta por hacer.
 */
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    /** abiertas · hoy · atrasadas · en_ruta · entregadas · canceladas · todas */
    public string $filtro = 'abiertas';

    /** Entrega sobre la que está actuando un modal. */
    public ?int $entregaId = null;

    public ?int $repartidorId = null;

    public string $recibidaPor = '';

    public bool $instalada = false;

    public string $motivo = '';

    public string $nuevaFecha = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('entregas.ver') ?? false, 403);
    }

    private function puedeGestionar(): bool
    {
        return auth()->user()?->can('entregas.gestionar') ?? false;
    }

    private function servicio(): ProgramacionDeEntregas
    {
        return app(ProgramacionDeEntregas::class);
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltro(): void
    {
        $this->resetPage();
    }

    // ---- Indicadores --------------------------------------------------------

    #[Computed]
    public function paraHoy(): int
    {
        return Entrega::query()->deHoy()->count();
    }

    #[Computed]
    public function atrasadas(): int
    {
        return Entrega::query()->atrasadas()->count();
    }

    #[Computed]
    public function enRuta(): int
    {
        return Entrega::query()->where('estado', 'en_ruta')->count();
    }

    #[Computed]
    public function sinFecha(): int
    {
        return Entrega::query()->abiertas()->whereNull('programada_para')->count();
    }

    /**
     * Quién puede llevar una entrega: cualquier cuenta activa que pueda
     * gestionarlas. No hay un rol «repartidor» y no hacía falta inventarlo.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function repartidores(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u): bool => $u->can('entregas.gestionar'))
            ->values();
    }

    // ---- Acciones -----------------------------------------------------------

    private function entrega(): Entrega
    {
        abort_unless($this->puedeGestionar(), 403);

        $entrega = Entrega::find($this->entregaId);

        abort_if($entrega === null, 404);

        return $entrega;
    }

    public function abrirDespacho(int $id): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->entregaId = $id;
        $this->repartidorId = Entrega::find($id)?->repartidor_id ?? auth()->id();
        $this->resetValidation();

        $this->dispatch('abrir-modal-despachar-entrega');
    }

    public function despachar(): void
    {
        $this->ejecutar(
            fn (Entrega $e) => $this->servicio()->despachar($e, $this->repartidorId, auth()->id()),
            'despachar-entrega',
            'Entrega despachada.'
        );
    }

    public function abrirConfirmacion(int $id): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->entregaId = $id;
        // En blanco a propósito: el nombre de quien firma se teclea al
        // recibirlo, no se propone. Proponer el del cliente convertiría el
        // dato en un «aceptar» y dejaría de servir de constancia.
        $this->recibidaPor = '';
        $this->instalada = false;
        $this->resetValidation();

        $this->dispatch('abrir-modal-confirmar-entrega');
    }

    public function confirmar(): void
    {
        $this->validate(
            ['recibidaPor' => ['required', 'string', 'max:120']],
            ['recibidaPor.required' => 'Anota quién recibió el aparato.']
        );

        $this->ejecutar(
            fn (Entrega $e) => $this->servicio()->confirmar($e, $this->recibidaPor, $this->instalada),
            'confirmar-entrega',
            'Entrega confirmada.'
        );
    }

    public function abrirFallo(int $id): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->entregaId = $id;
        $this->motivo = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-fallar-entrega');
    }

    public function fallar(): void
    {
        $this->validate(
            ['motivo' => ['required', 'string', 'max:1000']],
            ['motivo.required' => 'Di por qué no se pudo entregar.']
        );

        $this->ejecutar(
            fn (Entrega $e) => $this->servicio()->fallar($e, $this->motivo),
            'fallar-entrega',
            'Anotado. La entrega queda para reprogramar.'
        );
    }

    public function abrirReprogramacion(int $id): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $entrega = Entrega::find($id);

        $this->entregaId = $id;
        $this->nuevaFecha = $entrega?->programada_para?->format('Y-m-d') ?? '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-reprogramar-entrega');
    }

    public function reprogramar(): void
    {
        $this->validate(
            ['nuevaFecha' => ['nullable', 'date', 'after_or_equal:today']],
            ['nuevaFecha.after_or_equal' => 'La nueva fecha no puede ser anterior a hoy.']
        );

        $this->ejecutar(
            fn (Entrega $e) => $this->servicio()->reprogramar($e, $this->nuevaFecha),
            'reprogramar-entrega',
            'Entrega reprogramada.'
        );
    }

    public function abrirCancelacion(int $id): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->entregaId = $id;
        $this->motivo = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-cancelar-entrega');
    }

    public function cancelar(): void
    {
        $this->ejecutar(
            fn (Entrega $e) => $this->servicio()->cancelar($e, $this->motivo),
            'cancelar-entrega',
            'Entrega cancelada. Sus aparatos se pueden volver a programar.'
        );
    }

    /**
     * Envoltorio común de las cinco acciones: permiso, servicio, cierre del
     * modal y aviso. Los errores de negocio del servicio se enseñan como
     * toast y dejan la pantalla como estaba, para poder corregir.
     *
     * @param  callable(Entrega): Entrega  $accion
     */
    private function ejecutar(callable $accion, string $modal, string $exito): void
    {
        $entrega = $this->entrega();

        try {
            $accion($entrega);
        } catch (RuntimeException $e) {
            $this->dispatch("cerrar-modal-{$modal}");
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['entregaId', 'motivo', 'recibidaPor', 'instalada', 'nuevaFecha']);

        $this->dispatch("cerrar-modal-{$modal}");
        $this->dispatch('toast', tipo: 'success', mensaje: $exito);
    }

    public function render(): View
    {
        $entregas = Entrega::query()
            ->with([
                'venta',
                'cliente.persona',
                'repartidor',
                'detalles.ventaDetalle.producto',
                'detalles.ventaDetalle.unidad',
            ])
            ->buscar($this->buscar)
            ->when($this->filtro === 'abiertas', fn (Builder $q) => $q->abiertas())
            ->when($this->filtro === 'hoy', fn (Builder $q) => $q->deHoy())
            ->when($this->filtro === 'atrasadas', fn (Builder $q) => $q->atrasadas())
            ->when($this->filtro === 'en_ruta', fn (Builder $q) => $q->where('estado', 'en_ruta'))
            ->when($this->filtro === 'entregadas', fn (Builder $q) => $q->where('estado', 'entregada'))
            ->when($this->filtro === 'canceladas', fn (Builder $q) => $q->where('estado', 'cancelada'))
            // Lo que tiene fecha manda, y lo más antiguo primero: es el orden
            // en que hay que resolverlas. Las de «cuando se pueda» van al
            // final, que es exactamente su prioridad.
            ->orderByRaw('programada_para IS NULL')
            ->orderBy('programada_para')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.entregas.index', [
            'entregas' => $entregas,
            'puedeGestionar' => $this->puedeGestionar(),
            'estados' => Entrega::ESTADOS,
        ]);
    }
}
