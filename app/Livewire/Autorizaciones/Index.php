<?php

namespace App\Livewire\Autorizaciones;

use App\Models\SolicitudDescuento;
use App\Support\AutorizacionDeDescuento;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Bandeja de autorizaciones de descuento.
 *
 * Aquí llegan las rebajas que un vendedor quiere hacer por debajo del mínimo
 * del producto. El administrador aprueba tal cual, sugiere otro monto o
 * rechaza con un motivo, y el POS del vendedor se actualiza solo.
 *
 * Todo se revalida en el servicio: la pantalla solo pinta y delega.
 */
class Index extends Component
{
    /** Solicitud cuyo monto se está sugiriendo. */
    public ?int $sugerirId = null;

    public string $montoSugerido = '';

    /** Solicitud que se está rechazando. */
    public ?int $rechazarId = null;

    public string $motivoRechazo = '';

    /** @return \Illuminate\Database\Eloquent\Collection<int, SolicitudDescuento> */
    #[Computed]
    public function pendientes()
    {
        return SolicitudDescuento::query()
            ->pendientes()
            ->with(['producto', 'unidad', 'solicitante'])
            ->orderBy('created_at')
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, SolicitudDescuento> */
    #[Computed]
    public function resueltas()
    {
        return SolicitudDescuento::query()
            ->whereIn('estado', SolicitudDescuento::RESUELTAS)
            ->with(['producto', 'solicitante', 'revisor'])
            ->latest('resuelto_en')
            ->limit(15)
            ->get();
    }

    /**
     * Llega una solicitud nueva por WebSocket: la lista se rehace y se avisa.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:autorizaciones,.SolicitudDeDescuentoCreada')]
    public function alLlegarSolicitud(array $payload = []): void
    {
        unset($this->pendientes);

        $this->dispatch('toast', tipo: 'warning', mensaje:
            'Nueva solicitud de descuento de '.($payload['vendedor'] ?? 'un vendedor').'.');
    }

    public function aprobar(int $id): void
    {
        $this->autorizar();

        $solicitud = SolicitudDescuento::findOrFail($id);

        try {
            app(AutorizacionDeDescuento::class)->resolver($solicitud, true, null, null, (int) auth()->id());
        } catch (RuntimeException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->olvidar();

        $this->dispatch('toast', tipo: 'success', mensaje: 'Descuento autorizado. El vendedor ya lo ve en su carrito.');
    }

    public function abrirSugerir(int $id): void
    {
        $this->autorizar();

        $solicitud = SolicitudDescuento::findOrFail($id);

        $this->rechazarId = null;
        $this->motivoRechazo = '';
        $this->sugerirId = $id;
        $this->montoSugerido = number_format((float) $solicitud->precio_solicitado, 2, '.', '');
        $this->resetValidation();
    }

    public function confirmarSugerir(): void
    {
        $this->autorizar();

        if ($this->sugerirId === null) {
            return;
        }

        $this->validate(
            ['montoSugerido' => ['required', 'numeric', 'min:0.01', 'max:99999999']],
            ['montoSugerido.required' => 'Escribe el monto que autorizas.']
        );

        $solicitud = SolicitudDescuento::findOrFail($this->sugerirId);

        try {
            app(AutorizacionDeDescuento::class)->resolver(
                $solicitud, true, $this->montoSugerido, null, (int) auth()->id()
            );
        } catch (RuntimeException $e) {
            $this->addError('montoSugerido', $e->getMessage());

            return;
        }

        $this->cerrarFormularios();
        $this->olvidar();

        $this->dispatch('toast', tipo: 'success', mensaje: 'Precio autorizado y enviado al vendedor.');
    }

    public function abrirRechazar(int $id): void
    {
        $this->autorizar();

        $this->sugerirId = null;
        $this->montoSugerido = '';
        $this->rechazarId = $id;
        $this->motivoRechazo = '';
        $this->resetValidation();
    }

    public function confirmarRechazar(): void
    {
        $this->autorizar();

        if ($this->rechazarId === null) {
            return;
        }

        $this->validate(
            ['motivoRechazo' => ['required', 'string', 'min:3', 'max:255']],
            ['motivoRechazo.required' => 'Explica por qué se rechaza: el vendedor lo verá.']
        );

        $solicitud = SolicitudDescuento::findOrFail($this->rechazarId);

        try {
            app(AutorizacionDeDescuento::class)->resolver(
                $solicitud, false, null, $this->motivoRechazo, (int) auth()->id()
            );
        } catch (RuntimeException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->cerrarFormularios();
        $this->olvidar();

        $this->dispatch('toast', tipo: 'success', mensaje: 'Solicitud rechazada.');
    }

    public function cerrarFormularios(): void
    {
        $this->reset(['sugerirId', 'montoSugerido', 'rechazarId', 'motivoRechazo']);
        $this->resetValidation();
    }

    /** Suelta la caché de las listas tras resolver. */
    private function olvidar(): void
    {
        unset($this->pendientes, $this->resueltas);
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()?->can('ventas.autorizar_descuento') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.autorizaciones.index');
    }
}
