<?php

namespace App\Livewire\Catalogo;

use App\Support\ImportadorCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pantalla de carga masiva del catálogo desde el panel.
 *
 * Descarga la plantilla y recibe el Excel relleno. Todo el trabajo pesado —leer
 * el `.xlsx`, resolver la jerarquía y guardar— está en
 * `App\Support\ImportadorCatalogo`, para que el panel y la API se comporten
 * igual con el mismo archivo.
 */
class Importar extends Component
{
    use WithFileUploads;

    /** Excel subido, todavía en su carpeta temporal. */
    public $archivo = null;

    /** Resumen de la última importación. */
    public ?array $resultado = null;

    /** Error general de lectura o validación del archivo. */
    public ?string $error = null;

    public function mount(): void
    {
        $this->autorizar();
    }

    /**
     * Descarga la plantilla. La genera el servidor al momento para que siempre
     * refleje el formato que el importador entiende.
     */
    public function descargarPlantilla(ImportadorCatalogo $importador): StreamedResponse
    {
        $this->autorizar();

        $contenido = $importador->plantilla();

        return response()->streamDownload(
            fn () => print $contenido,
            'plantilla-catalogo.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function importar(ImportadorCatalogo $importador): void
    {
        $this->autorizar();
        $this->resultado = null;
        $this->error = null;

        // Por extensión y no por MIME: un .xlsx es un ZIP y el detector de
        // contenido puede clasificarlo como `application/zip`.
        $validador = Validator::make(
            ['archivo' => $this->archivo],
            ['archivo' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv']],
            [
                'archivo.required' => 'Selecciona el archivo de Excel.',
                'archivo.max' => 'El archivo no puede pesar más de 5 MB.',
                'archivo.extensions' => 'El archivo debe ser Excel (.xlsx) o CSV.',
            ],
        );

        if ($validador->fails()) {
            $this->error = $validador->errors()->first('archivo');

            return;
        }

        try {
            $resumen = $importador->importar($this->archivo->getRealPath());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->error = $e->validator->errors()->first('archivo') ?: 'No se pudo importar el archivo.';

            return;
        }

        $this->resultado = $resumen;
        $this->archivo = null;
        $this->dispatch('toast', tipo: 'success', mensaje: 'Catálogo importado correctamente.');
    }

    public function limpiar(): void
    {
        $this->reset(['archivo', 'resultado', 'error']);
    }

    public function render(): View
    {
        return view('livewire.catalogo.importar');
    }

    /**
     * El importador escribe productos y categorías; con poder crear una de las
     * dos basta, pero el usuario tiene que estar autenticado.
     */
    private function autorizar(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario !== null && ($usuario->can('productos.crear') || $usuario->can('categorias.crear')),
            403,
        );
    }
}
