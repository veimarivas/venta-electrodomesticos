{{--
    Orden de servicio técnico, en PDF (DomPDF).

    Es el papel con el que el cliente vuelve a buscar su aparato: por eso lleva
    el número de orden bien visible y su firma al pie. DomPDF no entiende
    flexbox: todo va con tablas.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Orden {{ $reparacion->codigo }}</title>

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
        .codigo { font-size: 15pt; font-weight: bold; margin-top: 4pt; }

        .separador { border-top: 1px solid #000; margin: 10pt 0; height: 0; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3pt 4pt; vertical-align: top; }

        .datos td { padding: 1.5pt 0; }
        .datos .etiqueta { color: #555; width: 38%; }

        .caja {
            border: 1px solid #999;
            padding: 6pt;
            margin-top: 4pt;
        }

        .garantia {
            border: 1.5pt solid #0a7a3f;
            color: #0a7a3f;
            padding: 4pt;
            text-align: center;
            font-weight: bold;
            letter-spacing: 1pt;
            margin: 6pt 0;
        }

        .firma {
            margin-top: 28pt;
            border-top: .7pt solid #000;
            width: 55%;
            padding-top: 3pt;
            font-size: 8pt;
            color: #555;
            text-align: center;
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
        <div class="titulo">ORDEN DE SERVICIO TÉCNICO</div>
        <div class="codigo">{{ $reparacion->codigo }}</div>
    </div>

    <div class="separador"></div>

    @if ($reparacion->en_garantia)
        <div class="garantia">EN GARANTÍA — SIN COSTO</div>
    @endif

    <table class="datos">
        <tr>
            <td class="etiqueta">Cliente</td>
            <td class="derecha fuerte">
                {{ $reparacion->cliente?->persona?->nombre_completo ?? $reparacion->entregada_a ?? 'Sin nombre' }}
            </td>
        </tr>
        <tr>
            <td class="etiqueta">Recibido el</td>
            <td class="derecha">{{ $reparacion->recibida_en?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Prometido para</td>
            <td class="derecha">{{ $reparacion->prometida_para?->format('d/m/Y') ?? 'Cuando esté listo' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Estado</td>
            <td class="derecha fuerte">
                {{ \App\Models\Reparacion::ESTADOS[$reparacion->estado] ?? $reparacion->estado }}
            </td>
        </tr>
        <tr>
            <td class="etiqueta">Recibió</td>
            <td class="derecha">{{ $reparacion->recibidaPor?->name ?? '—' }}</td>
        </tr>
    </table>

    <div class="separador"></div>

    <div class="fuerte">Aparato</div>
    <table class="datos">
        <tr>
            <td class="etiqueta">Producto</td>
            <td class="derecha">{{ $reparacion->unidad?->producto?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Código interno</td>
            <td class="derecha">{{ $reparacion->unidad?->codigo_interno ?? '—' }}</td>
        </tr>
        @if ($reparacion->unidad?->serial)
            <tr>
                <td class="etiqueta">Serial</td>
                <td class="derecha">{{ $reparacion->unidad->serial }}</td>
            </tr>
        @endif
        @if ($reparacion->unidad?->producto?->marca?->nombre)
            <tr>
                <td class="etiqueta">Marca</td>
                <td class="derecha">{{ $reparacion->unidad->producto->marca->nombre }}</td>
            </tr>
        @endif
    </table>

    <div class="separador"></div>

    <div class="fuerte">Falla reportada por el cliente</div>
    <div class="caja">{{ $reparacion->falla_reportada }}</div>

    @if ($reparacion->diagnostico)
        <div class="fuerte" style="margin-top: 8pt;">Diagnóstico del taller</div>
        <div class="caja">{{ $reparacion->diagnostico }}</div>
    @endif

    @if ($reparacion->trabajo_realizado)
        <div class="fuerte" style="margin-top: 8pt;">Trabajo realizado</div>
        <div class="caja">{{ $reparacion->trabajo_realizado }}</div>
    @endif

    <div class="separador"></div>

    <table class="datos">
        <tr class="fuerte">
            <td>COSTO</td>
            <td class="derecha">
                @if ($reparacion->en_garantia)
                    Bs 0,00 (garantía)
                @else
                    Bs {{ number_format((float) $reparacion->costo, 2, ',', '.') }}
                @endif
            </td>
        </tr>
        @if ($reparacion->garantia_hasta)
            <tr>
                <td class="etiqueta">Cobertura hasta</td>
                <td class="derecha">{{ $reparacion->garantia_hasta->format('d/m/Y') }}</td>
            </tr>
        @endif
    </table>

    @if ($reparacion->entregada_en)
        <div class="separador"></div>
        <table class="datos">
            <tr>
                <td class="etiqueta">Entregado el</td>
                <td class="derecha">{{ $reparacion->entregada_en->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Recibió conformes</td>
                <td class="derecha fuerte">{{ $reparacion->entregada_a ?? '—' }}</td>
            </tr>
        </table>
    @endif

    <table>
        <tr>
            <td style="width: 45%"></td>
            <td style="width: 10%"></td>
            <td class="firma">Firma de conformidad</td>
        </tr>
    </table>

    <div class="pie">
        Presente esta orden para retirar el aparato ·
        Emitida el {{ now()->format('d/m/Y H:i') }}
        <br>
        El aparato se conserva como máximo 90 días desde que se avisa que está listo.
    </div>

</body>

</html>
