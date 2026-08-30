<?php

namespace App\Livewire\Creditos;

use App\Models\Credito;
use App\Models\PagoCredito;
use App\Support\CobroDeCuota;
use App\Support\ProrrateoDeGastos;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * Estado de cuenta de un crédito, y la ventanilla donde se cobra.
 *
 * El cobro no pregunta qué cuota se paga: el dinero se imputa de la más
 * antigua a la más nueva. Dejar elegir permitiría saldar la de diciembre
 * dejando viva la de agosto, y la mora dejaría de significar nada.
 */
class Show extends Component
{
    public Credito $credito;

    /** Lo que el cliente entrega. Cadena para conservar lo tecleado. */
    public string $monto = '';

    public string $metodoPago = 'efectivo';

    public string $comprobante = '';

    public string $notas = '';

    public function mount(Credito $credito): void
    {
        abort_unless(auth()->user()?->can('creditos.ver') ?? false, 403);

        $this->credito = $credito;
    }

    private function puedeCobrar(): bool
    {
        return auth()->user()?->can('creditos.cobrar') ?? false;
    }

    /**
     * Abre la ventanilla con la cuota que toca ya escrita.
     *
     * Se propone el importe de la próxima cuota porque es lo que se cobra el
     * 95 % de las veces, pero se puede cambiar: hay clientes que traen dos
     * cuotas juntas y otros que traen la mitad.
     */
    public function abrirCobro(): void
    {
        abort_unless($this->puedeCobrar(), 403);

        $proxima = $this->credito->load('cuotas')->proximaCuota();

        $this->monto = $proxima !== null
            ? ProrrateoDeGastos::aDecimal($proxima->faltaEnCentavos())
            : '';

        $this->metodoPago = 'efectivo';
        $this->reset(['comprobante', 'notas']);
        $this->resetValidation();

        $this->dispatch('abrir-modal-cobrar-cuota');
    }

    public function cobrar(): void
    {
        abort_unless($this->puedeCobrar(), 403);

        $this->validate(
            [
                'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
                'metodoPago' => ['required', 'in:'.implode(',', array_keys(PagoCredito::METODOS_PAGO))],
                'comprobante' => [
                    in_array($this->metodoPago, PagoCredito::METODOS_CON_RESPALDO, true) ? 'required' : 'nullable',
                    'string', 'max:255',
                ],
                'notas' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'monto.required' => 'Escribe cuánto está entregando el cliente.',
                'monto.min' => 'El pago tiene que ser mayor que cero.',
                'comprobante.required' => 'Anota el número de comprobante del banco.',
            ]
        );

        try {
            $pagos = app(CobroDeCuota::class)->cobrar(
                $this->credito,
                $this->monto,
                [
                    'metodo_pago' => $this->metodoPago,
                    'comprobante_qr' => $this->comprobante,
                    'notas' => $this->notas,
                ],
                auth()->id(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-cobrar-cuota');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->credito = $this->credito->fresh();
        $this->reset(['monto', 'comprobante', 'notas']);

        $this->dispatch('cerrar-modal-cobrar-cuota');

        // El mensaje dice el recibo y si con esto quedó saldado: es lo que el
        // cajero necesita decirle al cliente sin abrir otra pantalla.
        $this->dispatch(
            'toast',
            tipo: 'success',
            mensaje: $this->credito->estado === 'pagado'
                ? "Recibo {$pagos->first()->recibo}. Crédito pagado por completo."
                : "Recibo {$pagos->first()->recibo} registrado."
        );
    }

    public function render(): View
    {
        $this->credito->load([
            'cliente.persona',
            'venta.detalles.producto',
            'cuotas',
            'pagos.cuota',
            'pagos.user',
            'creadoPor',
        ]);

        return view('livewire.creditos.show', [
            'puedeCobrar' => $this->puedeCobrar(),
            'proxima' => $this->credito->proximaCuota(),
            'metodos' => PagoCredito::METODOS_PAGO,
        ]);
    }
}
