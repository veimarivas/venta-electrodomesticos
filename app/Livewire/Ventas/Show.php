<?php

namespace App\Livewire\Ventas;

use App\Models\Venta;
use App\Models\VentaDetalle;
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
        ]);
    }
}
