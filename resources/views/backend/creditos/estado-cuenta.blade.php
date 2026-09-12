{{--
    Estado de cuenta de un crédito, en PDF (DomPDF).

    DomPDF trae el CSS de HTML 4 + CSS 2.1: nada de flexbox ni grid. La
    maquetación va con tablas a propósito; cambiarla por divs la rompe sin
    avisar. 'DejaVu Sans' es la única familia que trae acentos y ñ.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Estado de cuenta</title>

    <style>
        @page { margin: 30pt; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #000;
            margin: 0;
        }

        .centro { text-align: center; }
        .derecha { text-align: right; }
        .fuerte { font-weight: bold; }
        .tenue { color: #555; }

        h1 { font-size: 15pt; margin: 0; }
        .titulo { font-size: 10pt; letter-spacing: 2pt; margin-top: 2pt; }
        .codigo { font-size: 11pt; font-weight: bold; margin-top: 4pt; }

        .separador { border-top: 1px solid #000; margin: 10pt 0; height: 0; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3pt 4pt; vertical-align: top; }

        .datos td { padding: 1.5pt 0; }
        .datos .etiqueta { color: #555; width: 42%; }

        .plan th {
            background: #eee;
            border-bottom: 1px solid #999;
            font-size: 8pt;
            text-align: left;
        }
        .plan td { border-bottom: .5pt solid #ddd; }
        .plan .num { text-align: right; }

        .estado { font-weight: bold; }
        .pago { color: #0a7a3f; }
        .mora { color: #b00020; }

        .saldo-final td {
            border-top: 1.5pt solid #000;
            font-size: 12pt;
            font-weight: bold;
            padding-top: 6pt;
        }

        .pie {
            margin-top: 16pt;
            font-size: 7.5pt;
            color: #555;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>

<body>

    <div class="centro">
        <h1>{{ $tienda }}</h1>
        <div class="titulo">ESTADO DE CUENTA</div>
        <div class="codigo">
            Crédito de la venta {{ $credito->venta?->codigo ?? '—' }}
        </div>
    </div>

    <div class="separador"></div>

    <table class="datos">
        <tr>
            <td class="etiqueta">Cliente</td>
            <td class="derecha fuerte">
                {{ $credito->cliente?->persona?->nombre_completo ?? 'Sin nombre' }}
            </td>
        </tr>
        <tr>
            <td class="etiqueta">Carnet</td>
            <td class="derecha">{{ $credito->cliente?->persona?->carnet ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Emitido</td>
            <td class="derecha">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Estado</td>
            <td class="derecha fuerte">
                {{ \App\Models\Credito::ESTADOS[$credito->estado] ?? $credito->estado }}
            </td>
        </tr>
    </table>

    <div class="separador"></div>

    <table class="datos">
        <tr>
            <td class="etiqueta">Cuota inicial</td>
            <td class="derecha">Bs {{ number_format((float) $credito->cuota_inicial, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Total financiado</td>
            <td class="derecha">Bs {{ number_format((float) $credito->total_financiado, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Número de cuotas</td>
            <td class="derecha">{{ $credito->numero_cuotas }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Primer vencimiento</td>
            <td class="derecha">{{ $credito->primer_vencimiento?->format('d/m/Y') ?? '—' }}</td>
        </tr>
    </table>

    <div class="separador"></div>

    <table class="plan">
        <thead>
            <tr>
                <th style="width: 8%">#</th>
                <th style="width: 20%">Vence</th>
                <th class="num">Cuota</th>
                <th class="num">Pagado</th>
                <th class="num">Falta</th>
                <th style="width: 20%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($credito->cuotas as $cuota)
                <tr>
                    <td>{{ $cuota->numero }}</td>
                    <td>{{ $cuota->vence_en?->format('d/m/Y') }}</td>
                    <td class="num">{{ number_format((float) $cuota->monto, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $cuota->monto_pagado, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $cuota->falta, 2, ',', '.') }}</td>
                    <td class="estado {{ $cuota->esta_vencida ? 'mora' : ($cuota->esta_pagada ? 'pago' : '') }}">
                        {{ $cuota->etiqueta_estado }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="separador"></div>

    <table>
        <tr class="saldo-final">
            <td>SALDO PENDIENTE</td>
            <td class="derecha">Bs {{ number_format((float) $credito->saldo, 2, ',', '.') }}</td>
        </tr>
    </table>

    @if ($credito->pagos->isNotEmpty())
        <div class="separador"></div>

        <div class="fuerte">Pagos recibidos</div>
        <table class="plan">
            <thead>
                <tr>
                    <th style="width: 22%">Fecha</th>
                    <th style="width: 20%">Recibo</th>
                    <th>Método</th>
                    <th class="num">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($credito->pagos as $pago)
                    <tr>
                        <td>{{ $pago->pagado_en?->format('d/m/Y') }}</td>
                        <td>{{ $pago->recibo }}</td>
                        <td>{{ \App\Models\PagoCredito::METODOS_PAGO[$pago->metodo_pago] ?? $pago->metodo_pago }}</td>
                        <td class="num">{{ number_format((float) $pago->monto, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="pie">
        Documento informativo · Emitido el {{ now()->format('d/m/Y H:i') }}
        <br>
        Conserve este estado de cuenta para cualquier consulta.
    </div>

</body>

</html>
