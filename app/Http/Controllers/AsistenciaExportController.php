<?php

namespace App\Http\Controllers;

use App\Models\Tienda;
use App\Models\User;
use App\Support\RegistroDeAsistencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Historial de asistencia del mes para descargar: PDF (para imprimir y
 * firmar) o CSV (para abrir en Excel). Mismos filtros que la pantalla.
 */
class AsistenciaExportController extends Controller
{
    public function __invoke(Request $request, RegistroDeAsistencia $registro): Response
    {
        $datos = $request->validate([
            'formato' => ['required', 'in:pdf,csv'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'trabajador' => ['nullable', 'integer'],
            'tienda' => ['nullable', 'integer'],
        ]);

        $inicio = Carbon::createFromFormat('Y-m', $datos['mes'] ?? now()->format('Y-m'))->startOfMonth();
        $filas = $registro->historial(
            (int) $inicio->year,
            (int) $inicio->month,
            isset($datos['trabajador']) ? (int) $datos['trabajador'] : null,
            isset($datos['tienda']) ? (int) $datos['tienda'] : null,
        );

        $nombre = 'asistencia-'.$inicio->format('Y-m');

        if ($datos['formato'] === 'csv') {
            return $this->csv($filas, $nombre);
        }

        return Pdf::loadView('backend.asistencia.pdf', [
            'filas' => $filas,
            'inicio' => $inicio,
            'trabajador' => isset($datos['trabajador']) ? User::find($datos['trabajador'])?->name : null,
            'tienda' => isset($datos['tienda']) ? Tienda::withTrashed()->find($datos['tienda'])?->nombre : null,
            'tiendaNombre' => config('app.nombre_comercial', config('app.name')),
        ])->setPaper('letter')->stream($nombre.'.pdf');
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function csv(array $filas, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas): void {
            $salida = fopen('php://output', 'w');
            // BOM para que Excel lea las tildes; punto y coma, que es el
            // separador que espera un Excel en español.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Trabajador', 'Fecha', 'Tienda', 'Entrada', 'Salida', 'Horas', 'Minutos trabajados', 'Minutos de atraso', 'Observación'], ';');

            foreach ($filas as $f) {
                foreach ($f['detalle'] as $dia) {
                    foreach ($dia['turnos'] as $t) {
                        fputcsv($salida, [
                            $f['trabajador'],
                            Carbon::parse($t['fecha'])->format('d/m/Y'),
                            $t['tienda'],
                            Carbon::parse($t['entrada_en'])->format('H:i'),
                            $t['salida_en'] ? Carbon::parse($t['salida_en'])->format('H:i') : '',
                            RegistroDeAsistencia::horas($t['minutos']),
                            $t['minutos'] ?? '',
                            $t['minutos_atraso'] ?? '',
                            $t['sin_salida'] ? 'Sin salida' : ($t['corregida'] ? 'Salida corregida: '.$t['notas'] : ''),
                        ], ';');
                    }
                }
            }

            fclose($salida);
        }, $nombre.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
