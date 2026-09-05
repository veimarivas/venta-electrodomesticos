<?php

namespace App\Livewire\Inventario;

use App\Models\MovimientoInventario;
use App\Models\Unidad;
use App\Support\Kardex as RegistroKardex;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kardex del inventario: la historia de cada aparato.
 *
 * Dos modos en la misma pantalla:
 *
 *  · Sin unidad abierta, un listado de todos los movimientos, filtrable por
 *    tipo — sirve para revisar qué se movió hoy.
 *  · Con una unidad abierta (buscada por serial o código interno), su ficha
 *    completa y su historia, más el ajuste de estado.
 *
 * El buscador está pensado para teclear —o escanear— el serial que trae el
 * aparato, que es el dato que se tiene delante cuando alguien pregunta por él.
 */
class Kardex extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    /** Serial, código interno, SKU o nombre del producto. */
    public string $buscar = '';

    /** Filtro del listado general de movimientos. */
    public string $tipoFiltro = '';

    /** Unidad abierta en la ficha; null = listado general. */
    public ?int $unidadId = null;

    // ---- Ajuste de estado -------------------------------------------------

    public string $nuevoEstado = '';

    public string $motivo = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nuevoEstado' => ['required', Rule::in(array_keys(Unidad::ESTADOS))],
            // El motivo es obligatorio: un ajuste sin explicación no sirve de
            // auditoría, que es justamente para lo que existe el kardex.
            'motivo' => ['required', 'string', 'min:4', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nuevoEstado.required' => 'Elige el estado al que pasa la unidad.',
            'nuevoEstado.in' => 'Ese estado no es válido.',
            'motivo.required' => 'Explica por qué se ajusta la unidad.',
            'motivo.min' => 'El motivo debe explicar algo: al menos 4 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['nuevoEstado' => 'estado', 'motivo' => 'motivo'];
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedTipoFiltro(): void
    {
        $this->resetPage();
    }

    /** Unidad abierta, con todo lo necesario para su ficha. */
    #[Computed]
    public function unidad(): ?Unidad
    {
        return $this->unidadId === null
            ? null
            : Unidad::with(['producto.categoria', 'producto.marca'])->find($this->unidadId);
    }

    /**
     * Historia completa de la unidad abierta, de lo más reciente a lo más
     * antiguo. No se pagina: la vida de un aparato son unas pocas líneas.
     *
     * @return Collection<int, MovimientoInventario>
     */
    #[Computed]
    public function historia(): Collection
    {
        return $this->unidadId === null
            ? new Collection
            : MovimientoInventario::with(['user', 'origen'])
                ->where('unidad_id', $this->unidadId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
    }

    /**
     * Resultados del buscador, mientras no haya una unidad abierta.
     *
     * @return Collection<int, Unidad>
     */
    #[Computed]
    public function coincidencias(): Collection
    {
        $termino = trim($this->buscar);

        // Con menos de dos caracteres devolvería medio almacén.
        if (mb_strlen($termino) < 2 || $this->unidadId !== null) {
            return new Collection;
        }

        return Unidad::query()
            ->with('producto')
            ->where(function ($q) use ($termino) {
                $q->where('serial', 'like', "%{$termino}%")
                    ->orWhere('codigo_interno', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$termino}%"));
            })
            ->orderBy('codigo_interno')
            ->limit(15)
            ->get();
    }

    /**
     * Si la búsqueda encuentra exactamente una unidad por serial o código
     * interno, se abre sola: al escanear un serial lo que se quiere es su
     * ficha, no una lista de un elemento.
     */
    #[Computed]
    public function coincidenciaExacta(): ?Unidad
    {
        $termino = trim($this->buscar);

        if ($termino === '') {
            return null;
        }

        return Unidad::where('serial', $termino)
            ->orWhere('codigo_interno', $termino)
            ->first();
    }

    public function abrirUnidad(int $id): void
    {
        $this->autorizar('inventario.ver');

        $this->unidadId = $id;
        $this->nuevoEstado = Unidad::find($id)?->estado ?? '';
        $this->motivo = '';

        $this->resetValidation();
        unset($this->unidad, $this->historia);
    }

    public function cerrarUnidad(): void
    {
        $this->reset(['unidadId', 'nuevoEstado', 'motivo']);
        $this->resetValidation();
        $this->resetPage();
    }

    /**
     * Ajusta el estado de la unidad y lo deja registrado en el kardex.
     */
    public function ajustar(): void
    {
        $this->autorizar('inventario.ajustar');

        $unidad = $this->unidad;

        if ($unidad === null) {
            return;
        }

        $datos = $this->validate();

        if ($datos['nuevoEstado'] === $unidad->estado) {
            $this->addError('nuevoEstado', 'La unidad ya está en ese estado.');

            return;
        }

        $estadoAnterior = $unidad->estado;

        $unidad->update([
            'estado' => $datos['nuevoEstado'],
            // Marcar como vendida fuera del módulo de ventas es una
            // regularización; se sella la fecha para que el histórico no
            // quede con una venta sin momento.
            'vendido_en' => $datos['nuevoEstado'] === 'vendido' ? now() : null,
        ]);

        app(RegistroKardex::class)->cambioDeEstado(
            $unidad->refresh(),
            $estadoAnterior,
            notas: $datos['motivo'],
        );

        $this->motivo = '';
        $this->resetValidation();

        unset($this->unidad, $this->historia);

        $this->dispatch('toast', tipo: 'success', mensaje: 'Ajuste registrado en el kardex.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function render(): View
    {
        // Listado general: solo cuando no hay una unidad abierta.
        $movimientos = MovimientoInventario::query()
            ->with(['unidad.producto', 'user'])
            ->deTipo($this->tipoFiltro)
            ->when(trim($this->buscar) !== '', function ($q) {
                $termino = trim($this->buscar);

                $q->whereHas('unidad', fn ($u) => $u->where('serial', 'like', "%{$termino}%")
                    ->orWhere('codigo_interno', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$termino}%")));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.inventario.kardex', [
            'movimientos' => $movimientos,
            'tipos' => MovimientoInventario::TIPOS,
            'estados' => Unidad::ESTADOS,
            'totalMovimientos' => MovimientoInventario::count(),
            'movimientosHoy' => MovimientoInventario::whereDate('created_at', today())->count(),
            'entradasDelMes' => MovimientoInventario::where('tipo', 'entrada')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'ajustesDelMes' => MovimientoInventario::whereIn('tipo', ['ajuste', 'dano', 'devolucion'])
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ]);
    }
}
