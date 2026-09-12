{{--
    Etiqueta de una unidad, en PDF (DomPDF).

    Una sola página del tamaño exacto del adhesivo. DomPDF no entiende flexbox:
    la maquetación va con una tabla. 'DejaVu Sans' es la única familia que trae
    acentos y ñ.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Etiqueta {{ $unidad->codigo_interno }}</title>

    <style>
        @page { margin: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0;
        }

        .etiqueta { padding: 3mm; }

        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: middle; padding: 0; }

        /* El margen de 3mm alrededor del QR es su zona de silencio. */
        .qr img {
            width: {{ $qrMm }}mm;
            height: {{ $qrMm }}mm;
            display: block;
        }

        .datos { padding-left: 2mm; }

        .producto {
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.2;
        }

        .codigo {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8pt;
            margin-top: 1.5mm;
        }

        .serial {
            font-size: 7pt;
            color: #555;
            margin-top: 1mm;
        }
    </style>
</head>

<body>
    <div class="etiqueta">
        <table>
            <tr>
                <td class="qr"><img src="{{ $qr }}" alt=""></td>
                <td class="datos">
                    <div class="producto">{{ $unidad->producto?->nombre }}</div>
                    <div class="codigo">{{ $unidad->codigo_interno }}</div>
                    @if ($unidad->serial)
                        <div class="serial">S/N {{ $unidad->serial }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
