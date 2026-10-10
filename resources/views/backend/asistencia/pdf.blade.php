@php
    use App\Support\RegistroDeAsistencia as R;
    use Illuminate\Support\Carbon;

    $hora = fn (?string $iso): string => $iso ? Carbon::parse($iso)->format('H:i') : '—';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Asistencia {{ $inicio->translatedFormat('F Y') }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1c2533; }
        h1 { font-size: 16px; margin: 0; color: #10233c; }
        .sub { color: #6b7686; margin: 2px 0 14px; }
        .trabajador { margin-top: 16px; page-break-inside: avoid; }
        .trabajador h2 { font-size: 12px; margin: 0 0 4px; padding: 5px 8px; background: #10233c; color: #fff; }
        .resumen { margin: 0 0 6px; color: #3b4656; }
        .resumen b { color: #10233c; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8.5px; text-transform: uppercase; color: #6b7686; border-bottom: 1px solid #c9d1dc; padding: 4px; }
        td { padding: 4px; border-bottom: 1px solid #edf0f4; }
        .der { text-align: right; }
        .tarde { color: #b06b00; font-weight: bold; }
        .falta { color: #c0392b; font-weight: bold; }
        .nota { color: #6b7686; font-size: 8.5px; }
        .firma { margin-top: 26px; width: 45%; border-top: 1px solid #1c2533; padding-top: 4px; text-align: center; color: #6b7686; }
        .pie { position: fixed; bottom: -12px; left: 0; right: 0; text-align: center; font-size: 8px; color: #9aa3af; }
    </style>
</head>
<body>
    <h1>{{ $tiendaNombre }} · Asistencia de {{ $inicio->translatedFormat('F \d\e Y') }}</h1>
    <p class="sub">
        {{ $trabajador ?? 'Todo el personal' }}{{ $tienda ? ' · '.$tienda : '' }}
        · Emitido el {{ now()->format('d/m/Y H:i') }}
    </p>

    @forelse ($filas as $f)
        <div class="trabajador">
            <h2>{{ $f['trabajador'] }}</h2>
            <p class="resumen">
                <b>{{ $f['dias'] }}</b> días · <b>{{ R::horas($f['minutos']) }}</b> trabajadas ·
                <b>{{ $f['atrasos'] }}</b> atrasos ({{ R::horas($f['minutos_atraso']) }}) ·
                <b>{{ $f['sin_salida'] }}</b> sin salida
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tienda</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th class="der">Horas</th>
                        <th class="der">Atraso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($f['detalle'] as $dia)
                        @foreach ($dia['turnos'] as $t)
                            <tr>
                                <td>{{ $loop->first ? Carbon::parse($dia['fecha'])->translatedFormat('D d/m') : '' }}</td>
                                <td>{{ $t['tienda'] }}</td>
                                <td>{{ $hora($t['entrada_en']) }}</td>
                                <td>
                                    @if ($t['salida_en'])
                                        {{ $hora($t['salida_en']) }}
                                        @if ($t['corregida']) <span class="nota">(corregida: {{ $t['notas'] }})</span> @endif
                                    @elseif ($t['sin_salida'])
                                        <span class="falta">Sin salida</span>
                                    @else
                                        En turno
                                    @endif
                                </td>
                                <td class="der">{{ R::horas($t['minutos']) }}</td>
                                <td class="der">
                                    @if (($t['minutos_atraso'] ?? 0) > 0)
                                        <span class="tarde">{{ $t['minutos_atraso'] }} min</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            @if ($trabajador)
                <div class="firma">Firma de {{ $f['trabajador'] }}</div>
            @endif
        </div>
    @empty
        <p>Sin asistencia registrada en el mes con esos filtros.</p>
    @endforelse

    <div class="pie">Marcado desde el teléfono dentro del radio de cada tienda · {{ $tiendaNombre }}</div>
</body>
</html>
