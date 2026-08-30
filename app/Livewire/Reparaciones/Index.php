<?php

namespace App\Livewire\Reparaciones;

use App\Models\Reparacion;
use App\Models\Unidad;
use App\Models\User;
use App\Support\ServicioTecnico;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * El taller: qué hay dentro, en qué anda cada cosa y qué se prometió para hoy.
 *
 * La recepción vive aquí y no en otra pantalla porque es el mismo acto: el
 * cliente llega con el aparato, se busca su serial y se abre la orden sin
 * cambiar de sitio.
 */
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    /** abiertas · atrasadas · en_taller · listas · cerradas · todas */
    public string $filtro = 'abiertas';

    // ---- Recepción ---------------------------------------------------------

    /** Serial o código interno del aparato que trae el cliente. */
    public string $buscarUnidad = '';

    public ?int $unidadId = null;

    public string $fallaReportada = '';

    public string $prometidaPara = '';

    public string $costoEstimado = '';

    public ?int $tecnicoId = null;

    public string $notas = '';

    // ---- Acciones sobre una orden ------------------------------------------

    public ?int $reparacionId = null;

    public string $diagnostico = '';

    public string $trabajoRealizado = '';

    public string $motivo = '';

    public string $entregadaA = '';

    public string $costo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reparaciones.ver') ?? false, 403);
    }

    private function puedeRecibir(): bool
    {
        return auth()->user()?->can('reparaciones.recibir') ?? false;
    }

    private function puedeAtender(): bool
    {
        return auth()->user()?->can('reparaciones.atender') ?? false;
    }

    private function servicio(): ServicioTecnico
    {
        return app(ServicioTecnico::class);
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
    public function enTaller(): int
    {
        return Reparacion::query()->abiertas()->count();
    }

    #[Computed]
    public function atrasadas(): int
    {
        return Reparacion::query()->atrasadas()->count();
    }

    #[Computed]
    public function listas(): int
    {
        return Reparacion::query()->where('estado', 'lista')->count();
    }

    #[Computed]
    public function enGarantia(): int
    {
        return Reparacion::query()->abiertas()->where('en_garantia', true)->count();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function tecnicos(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u): bool => $u->can('reparaciones.atender'))
            ->values();
    }

    // ---- Recibir un aparato -------------------------------------------------

    /**
     * Aparatos que casan con lo tecleado.
     *
     * Se buscan por serial o código interno —lo que trae grabado la caja— y no
     * por producto: el taller trabaja sobre un aparato concreto, no sobre un
     * modelo.
     *
     * @return Collection<int, Unidad>
     */
    #[Computed]
    public function coincidencias(): Collection
    {
        $termino = trim($this->buscarUnidad);

        if (mb_strlen($termino) < 2) {
            return new Collection;
        }

        return Unidad::query()
            ->with('producto')
            ->where(fn (Builder $q) => $q->where('serial', 'like', "%{$termino}%")
                ->orWhere('codigo_interno', 'like', "%{$termino}%"))
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function unidadElegida(): ?Unidad
    {
        return $this->unidadId === null
            ? null
            : Unidad::with('producto')->find($this->unidadId);
    }

    /** La orden abierta del aparato elegido, si ya está en el taller. */
    #[Computed]
    public function ordenAbierta(): ?Reparacion
    {
        $unidad = $this->unidadElegida;

        return $unidad === null ? null : $this->servicio()->reparacionAbierta($unidad);
    }

    public function abrirRecepcion(): void
    {
        abort_unless($this->puedeRecibir(), 403);

        $this->reset(['buscarUnidad', 'unidadId', 'fallaReportada', 'prometidaPara', 'costoEstimado', 'tecnicoId', 'notas']);
        $this->resetValidation();

        $this->dispatch('abrir-modal-recibir-aparato');
    }

    public function elegirUnidad(int $id): void
    {
        abort_unless($this->puedeRecibir(), 403);

        $this->unidadId = $id;
        $this->buscarUnidad = '';
    }

    public function quitarUnidad(): void
    {
        $this->unidadId = null;
    }

    public function recibir(): void
    {
        abort_unless($this->puedeRecibir(), 403);

        $this->validate(
            [
                'unidadId' => ['required', 'integer'],
                'fallaReportada' => ['required', 'string', 'min:4', 'max:1000'],
                'prometidaPara' => ['nullable', 'date', 'after_or_equal:today'],
                'costoEstimado' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'notas' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'unidadId.required' => 'Busca y elige el aparato que trae el cliente.',
                'fallaReportada.required' => 'Anota qué le pasa según el cliente.',
                'fallaReportada.min' => 'Explica un poco más la falla.',
                'prometidaPara.after_or_equal' => 'La fecha prometida no puede ser anterior a hoy.',
            ]
        );

        $unidad = Unidad::with('producto')->find($this->unidadId);

        if ($unidad === null) {
            return;
        }

        try {
            $reparacion = $this->servicio()->recibir(
                $unidad,
                [
                    'falla_reportada' => $this->fallaReportada,
                    'prometida_para' => $this->prometidaPara,
                    'costo' => $this->costoEstimado ?: 0,
                    'tecnico_id' => $this->tecnicoId,
                    'notas' => $this->notas,
                ],
                auth()->id(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-recibir-aparato');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['buscarUnidad', 'unidadId', 'fallaReportada', 'prometidaPara', 'costoEstimado', 'tecnicoId', 'notas']);

        $this->dispatch('cerrar-modal-recibir-aparato');
        // El código va en el aviso porque es lo que se le apunta al cliente en
        // el papel con el que vuelve.
        $this->dispatch(
            'toast',
            tipo: 'success',
            mensaje: "Orden {$reparacion->codigo} abierta"
                .($reparacion->en_garantia ? ' — el aparato está en garantía.' : '.')
        );
    }

    // ---- Mover una orden ----------------------------------------------------

    private function orden(): Reparacion
    {
        abort_unless($this->puedeAtender(), 403);

        $reparacion = Reparacion::find($this->reparacionId);

        abort_if($reparacion === null, 404);

        return $reparacion;
    }

    public function abrirDiagnostico(int $id): void
    {
        abort_unless($this->puedeAtender(), 403);

        $reparacion = Reparacion::find($id);

        $this->reparacionId = $id;
        $this->diagnostico = $reparacion?->diagnostico ?? '';
        $this->costo = $reparacion?->en_garantia ? '' : (string) ($reparacion?->costo ?? '');
        $this->tecnicoId = $reparacion?->tecnico_id ?? auth()->id();
        $this->resetValidation();

        $this->dispatch('abrir-modal-diagnostico');
    }

    public function diagnosticar(): void
    {
        $this->validate(
            [
                'diagnostico' => ['required', 'string', 'min:4', 'max:1000'],
                'costo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            ],
            ['diagnostico.required' => 'Escribe qué encontraste.']
        );

        $this->ejecutar(
            fn (Reparacion $r) => $this->servicio()->diagnosticar(
                $r,
                $this->diagnostico,
                $this->tecnicoId,
                $this->costo === '' ? null : $this->costo,
            ),
            'diagnostico',
            'Diagnóstico anotado.'
        );
    }

    public function abrirEspera(int $id): void
    {
        abort_unless($this->puedeAtender(), 403);

        $this->reparacionId = $id;
        $this->motivo = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-esperar-repuesto');
    }

    public function esperarRepuesto(): void
    {
        $this->validate(
            ['motivo' => ['required', 'string', 'max:500']],
            ['motivo.required' => 'Di qué repuesto hace falta.']
        );

        $this->ejecutar(
            fn (Reparacion $r) => $this->servicio()->esperarRepuesto($r, $this->motivo),
            'esperar-repuesto',
            'Anotado. La orden queda esperando el repuesto.'
        );
    }

    public function abrirCierre(int $id): void
    {
        abort_unless($this->puedeAtender(), 403);

        $this->reparacionId = $id;
        $this->trabajoRealizado = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-listo');
    }

    public function marcarLista(): void
    {
        $this->validate(
            ['trabajoRealizado' => ['required', 'string', 'min:4', 'max:1000']],
            ['trabajoRealizado.required' => 'Anota qué se le hizo al aparato.']
        );

        $this->ejecutar(
            fn (Reparacion $r) => $this->servicio()->marcarLista($r, $this->trabajoRealizado),
            'listo',
            'Lista para que la recojan.'
        );
    }

    public function abrirIrreparable(int $id): void
    {
        abort_unless($this->puedeAtender(), 403);

        $this->reparacionId = $id;
        $this->motivo = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-irreparable');
    }

    public function declararIrreparable(): void
    {
        $this->validate(
            ['motivo' => ['required', 'string', 'min:4', 'max:1000']],
            ['motivo.required' => 'Di por qué no tiene arreglo.']
        );

        $this->ejecutar(
            fn (Reparacion $r) => $this->servicio()->declararIrreparable($r, $this->motivo),
            'irreparable',
            'Anotado. Avisa al cliente para que lo recoja.'
        );
    }

    /**
     * Entregar puede hacerlo quien está en el mostrador, no solo el técnico:
     * el cliente viene a recoger su aparato y no siempre hay alguien del
     * taller delante.
     */
    public function abrirEntrega(int $id): void
    {
        abort_unless($this->puedeRecibir() || $this->puedeAtender(), 403);

        $this->reparacionId = $id;
        $this->entregadaA = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-entregar-reparacion');
    }

    public function entregar(): void
    {
        abort_unless($this->puedeRecibir() || $this->puedeAtender(), 403);

        $this->validate(
            ['entregadaA' => ['required', 'string', 'max:120']],
            ['entregadaA.required' => 'Anota quién se lleva el aparato.']
        );

        $reparacion = Reparacion::find($this->reparacionId);

        abort_if($reparacion === null, 404);

        try {
            $this->servicio()->entregar($reparacion, $this->entregadaA);
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-entregar-reparacion');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['reparacionId', 'entregadaA']);

        $this->dispatch('cerrar-modal-entregar-reparacion');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Aparato entregado al cliente.');
    }

    public function abrirCancelacion(int $id): void
    {
        abort_unless($this->puedeAtender(), 403);

        $this->reparacionId = $id;
        $this->motivo = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-cancelar-reparacion');
    }

    public function cancelar(): void
    {
        $this->validate(
            ['motivo' => ['required', 'string', 'max:500']],
            ['motivo.required' => 'Di por qué se cancela la orden.']
        );

        $this->ejecutar(
            fn (Reparacion $r) => $this->servicio()->cancelar($r, $this->motivo),
            'cancelar-reparacion',
            'Orden cancelada. El aparato vuelve al estado en que entró.'
        );
    }

    /**
     * Envoltorio común: permiso, servicio, cierre del modal y aviso. Los
     * errores de negocio se enseñan como toast y dejan la pantalla como
     * estaba, para poder corregir.
     *
     * @param  callable(Reparacion): Reparacion  $accion
     */
    private function ejecutar(callable $accion, string $modal, string $exito): void
    {
        $reparacion = $this->orden();

        try {
            $accion($reparacion);
        } catch (RuntimeException $e) {
            $this->dispatch("cerrar-modal-{$modal}");
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['reparacionId', 'diagnostico', 'trabajoRealizado', 'motivo', 'costo']);

        $this->dispatch("cerrar-modal-{$modal}");
        $this->dispatch('toast', tipo: 'success', mensaje: $exito);
    }

    public function render(): View
    {
        $reparaciones = Reparacion::query()
            ->with(['unidad.producto', 'cliente.persona', 'tecnico', 'venta'])
            ->buscar($this->buscar)
            ->when($this->filtro === 'abiertas', fn (Builder $q) => $q->abiertas())
            ->when($this->filtro === 'atrasadas', fn (Builder $q) => $q->atrasadas())
            ->when($this->filtro === 'en_taller', fn (Builder $q) => $q->whereIn(
                'estado',
                ['recibida', 'en_reparacion', 'esperando_repuesto']
            ))
            ->when($this->filtro === 'listas', fn (Builder $q) => $q->where('estado', 'lista'))
            ->when($this->filtro === 'cerradas', fn (Builder $q) => $q->whereIn(
                'estado',
                Reparacion::ESTADOS_CERRADOS
            ))
            // Lo prometido manda, y lo más antiguo primero. Sin fecha al final:
            // es lo que nadie está esperando en una fecha concreta.
            ->orderByRaw('prometida_para IS NULL')
            ->orderBy('prometida_para')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.reparaciones.index', [
            'reparaciones' => $reparaciones,
            'puedeRecibir' => $this->puedeRecibir(),
            'puedeAtender' => $this->puedeAtender(),
            'estados' => Reparacion::ESTADOS,
        ]);
    }
}
