<?php

namespace App\Livewire\Ventas;

use App\Models\EntregaDetalle;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\ProgramacionDeEntregas;
use App\Support\RegistroDeVenta;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

class Show extends Component
{
    public Venta $venta;

    /** Línea que se está devolviendo; null = no hay ninguna en curso. */
    public ?int $detalleADevolver = null;

    public string $motivoDevolucion = '';

    // ---- Programar entrega -------------------------------------------------

    /** @var array<int, int|string> Líneas marcadas para el envío. */
    public array $lineasAEntregar = [];

    public string $direccion = '';

    public string $referencia = '';

    public string $telefonoContacto = '';

    public string $programadaPara = '';

    public bool $conInstalacion = false;

    public string $notasEntrega = '';

    public function mount(Venta $venta): void
    {
        abort_unless(auth()->user()?->can('ventas.ver') ?? false, 403);

        $this->venta = $venta;
    }

    /**
     * Abre el diálogo para devolver un aparato.
     *
     * Va con el permiso de anular y no con uno propio: devolver una línea es
     * una acción **más pequeña** que anular la venta entera, así que quien
     * puede lo más puede lo menos. Un permiso nuevo obligaría a repartirlo a
     * mano en cada rol antes de que nadie pudiera usar esto.
     */
    public function confirmarDevolucion(int $detalleId): void
    {
        $this->autorizar();

        $detalle = $this->venta->detalles->firstWhere('id', $detalleId);

        if ($detalle === null || $detalle->estaDevuelto()) {
            return;
        }

        $this->detalleADevolver = $detalleId;
        $this->motivoDevolucion = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-devolucion');
    }

    public function devolver(): void
    {
        $this->autorizar();

        if ($this->detalleADevolver === null) {
            return;
        }

        // El motivo es obligatorio aquí y en el servicio. Sin él, dentro de un
        // mes nadie sabrá si el aparato volvió fallado o si el cliente cambió
        // de idea, que son dos cosas muy distintas para el proveedor.
        $this->validate(
            ['motivoDevolucion' => ['required', 'string', 'min:4', 'max:255']],
            [
                'motivoDevolucion.required' => 'Escribe por qué se devuelve el aparato.',
                'motivoDevolucion.min' => 'Explica un poco más el motivo.',
            ]
        );

        $detalle = VentaDetalle::query()
            ->with(['venta', 'unidad'])
            ->find($this->detalleADevolver);

        if ($detalle === null) {
            return;
        }

        try {
            app(RegistroDeVenta::class)->devolver($detalle, $this->motivoDevolucion);
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-devolucion');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->detalleADevolver = null;
        $this->motivoDevolucion = '';

        $this->recargar();

        $this->dispatch('cerrar-modal-devolucion');
        $this->dispatch(
            'toast',
            tipo: 'success',
            mensaje: 'Aparato devuelto y de vuelta en el stock.'
        );
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()?->can('ventas.anular') ?? false, 403);
    }

    // =======================================================================
    // Programar entrega
    // =======================================================================

    /**
     * La entrega se programa desde aquí y no desde el punto de venta.
     *
     * En el mostrador lo que urge es cobrar; la dirección, la referencia y el
     * día se acuerdan después, con el cliente ya tranquilo. Además así se
     * puede programar la entrega de una venta de la semana pasada, que en el
     * POS sería imposible.
     */
    public function abrirEntrega(): void
    {
        $this->autorizarEntrega('entregas.crear');

        // Vienen marcados los aparatos que aún no están en ninguna entrega
        // viva: es lo que casi siempre se quiere llevar.
        $this->lineasAEntregar = $this->lineasEntregables()->pluck('id')->all();

        $this->direccion = '';
        $this->referencia = '';
        $this->telefonoContacto = $this->venta->cliente?->persona?->celular ?? '';
        $this->programadaPara = '';
        $this->conInstalacion = false;
        $this->notasEntrega = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-programar-entrega');
    }

    public function programarEntrega(): void
    {
        $this->autorizarEntrega('entregas.crear');

        $this->validate(
            [
                'direccion' => ['required', 'string', 'max:255'],
                'referencia' => ['nullable', 'string', 'max:255'],
                'telefonoContacto' => ['nullable', 'string', 'max:30'],
                'programadaPara' => ['nullable', 'date', 'after_or_equal:today'],
                'lineasAEntregar' => ['required', 'array', 'min:1'],
            ],
            [
                'direccion.required' => 'Escribe la dirección de entrega.',
                'programadaPara.after_or_equal' => 'La fecha de entrega no puede ser anterior a hoy.',
                'lineasAEntregar.required' => 'Elige al menos un aparato para entregar.',
                'lineasAEntregar.min' => 'Elige al menos un aparato para entregar.',
            ]
        );

        try {
            app(ProgramacionDeEntregas::class)->programar(
                $this->venta,
                array_map('intval', $this->lineasAEntregar),
                [
                    'direccion' => $this->direccion,
                    'referencia' => $this->referencia,
                    'telefono_contacto' => $this->telefonoContacto,
                    'programada_para' => $this->programadaPara,
                    'con_instalacion' => $this->conInstalacion,
                    'notas' => $this->notasEntrega,
                ],
                auth()->id(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-programar-entrega');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->recargar();

        $this->dispatch('cerrar-modal-programar-entrega');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Entrega programada.');
    }

    /**
     * Aparatos de esta venta que todavía no están en ninguna entrega viva.
     *
     * Uno ya programado no vuelve a ofrecerse: el índice único lo rechazaría
     * igualmente, pero enseñarlo invitaría a un error que después hay que
     * explicar.
     *
     * @return \Illuminate\Support\Collection<int, VentaDetalle>
     */
    public function lineasEntregables(): \Illuminate\Support\Collection
    {
        $yaProgramadas = EntregaDetalle::query()
            ->whereNotNull('venta_detalle_activo_id')
            ->pluck('venta_detalle_activo_id')
            ->all();

        return $this->venta->detalles
            ->whereNull('devuelto_en')
            ->whereNotIn('id', $yaProgramadas)
            ->values();
    }

    private function autorizarEntrega(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    /**
     * Vuelve a leer la venta tras devolver: cambian los importes de la
     * cabecera, el estado de la línea y —si era el último aparato— el de la
     * venta entera.
     */
    private function recargar(): void
    {
        $this->venta = $this->venta->fresh() ?? $this->venta;
    }

    public function render(): View
    {
        // Las relaciones se cargan AQUÍ y no en mount(): Livewire rehidrata el
        // modelo en cada petición y lo trae pelado, así que lo que se cargue al
        // montar no sobrevive al primer clic. Con `shouldBeStrict()` activo eso
        // no es una consulta de más, es una excepción en mitad de la vista.
        $this->venta->loadMissing([
            'detalles.unidad',
            'detalles.producto.marca',
            'cliente.persona',
            'user',
            'qrCobro',
            'credito.cuotas',
            'entregas.repartidor',
            'entregas.detalles.ventaDetalle.producto',
        ]);

        // Los aparatos devueltos siguen en la tabla como histórico, pero no
        // cuentan como vendidos: enseñar «3 aparatos» junto a un total de dos
        // descuadra la pantalla consigo misma.
        $devueltos = $this->venta->detalles->whereNotNull('devuelto_en')->count();

        return view('livewire.ventas.show', [
            'vendidos' => $this->venta->detalles->count() - $devueltos,
            'devueltos' => $devueltos,
            'estados' => Venta::ESTADOS,
            'metodosPago' => Venta::METODOS_PAGO,
            'puedeVerCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
            'puedeDevolver' => (auth()->user()?->can('ventas.anular') ?? false)
                && $this->venta->esta_completada,
            'puedeVerCredito' => auth()->user()?->can('creditos.ver') ?? false,
            'puedeVerEntregas' => auth()->user()?->can('entregas.ver') ?? false,
            // Programar exige que quede algo por llevar: el botón no se
            // ofrece si todos los aparatos ya están en una entrega viva.
            'puedeProgramarEntrega' => (auth()->user()?->can('entregas.crear') ?? false)
                && $this->venta->esta_completada
                && $this->lineasEntregables()->isNotEmpty(),
            'entregables' => $this->lineasEntregables(),
            'estadosEntrega' => \App\Models\Entrega::ESTADOS,
        ]);
    }
}
