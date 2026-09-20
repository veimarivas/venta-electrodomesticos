{{--
    Varias etiquetas en un PDF, una por página y del tamaño del adhesivo.

    Es el mismo diseño que la etiqueta suelta (`pdf.blade.php`); DomPDF no
    entiende flexbox, así que la maquetación va con una tabla y cada página se
    separa con `page-break-after`.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Etiquetas</title>

    <style>
        @page { margin: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0;
        }

        .pagina { page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }

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
    @foreach ($paginas as $pagina)
        <div class="pagina">
            <div class="etiqueta">
                <table>
                    <tr>
                        <td class="qr"><img src="{{ $pagina['qr'] }}" alt=""></td>
                        <td class="datos">
                            <div class="producto">{{ $pagina['unidad']->producto?->nombre }}</div>
                            <div class="codigo">{{ $pagina['unidad']->codigo_interno }}</div>
                            @if ($pagina['unidad']->serial)
                                <div class="serial">S/N {{ $pagina['unidad']->serial }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach
</body>

</html>
