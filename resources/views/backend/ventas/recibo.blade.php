{{--
    Recibo de venta en PDF (DomPDF).

    Ojo al tocarlo: DomPDF NO entiende flexbox ni grid, y su soporte de CSS es
    el de HTML 4 + CSS 2.1. Todo lo que hay aquí está montado con tablas y
    márgenes a propósito; cambiarlo por divs con flex rompe la maquetación sin
    dar ningún aviso.

    El ancho de página lo fija el controlador (80 mm, rollo de mostrador), así
    que aquí se trabaja al 100 % del ancho disponible.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Recibo {{ $venta->codigo }}</title>

    <style>
        @page { margin: 10pt 8pt; }

        body {
            /* DejaVu es la única familia que DomPDF trae con acentos y ñ. */
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7.5pt;
            color: #000;
            margin: 0;
        }

        .centro { text-align: center; }
        .derecha { text-align: right; }
        .fuerte { font-weight: bold; }
        .tenue { color: #555; }

        .tienda {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: .5pt;
        }

        .titulo {
            font-size: 8pt;
            letter-spacing: 2pt;
            margin-top: 2pt;
        }

        .codigo {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 6pt;
        }

        /* La línea de puntos es la separación de toda la vida de un ticket:
           se lee como corte incluso impresa en una hoja normal. */
        .separador {
            border-top: 1px dashed #000;
            margin: 6pt 0;
            height: 0;
        }

        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0; }

        .datos td { padding: 1pt 0; }
        .datos .etiqueta { color: #555; width: 42%; }

        .lineas td { padding: 2pt 0; }
        .lineas .detalle { color: #555; font-size: 6.5pt; }

        .totales td { padding: 1.5pt 0; }

        .total-final td {
            border-top: 1px solid #000;
            padding-top: 4pt;
            font-size: 10.5pt;
            font-weight: bold;
        }

        .anulada {
            border: 1.5pt solid #000;
            padding: 4pt;
            text-align: center;
            font-weight: bold;
            letter-spacing: 1pt;
            margin-bottom: 6pt;
        }

        .pie {
            margin-top: 8pt;
            font-size: 6.5pt;
            color: #555;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>

<body>

    @if ($venta->esta_anulada)
        {{-- Lo primero que se ve: un recibo anulado que parezca válido es un
             problema de caja, no de diseño. --}}
        <div class="anulada">
            ANULADA · {{ $venta->anulada_en?->format('d/m/Y H:i') }}
            @if ($venta->motivo_anulacion)
                <div style="font-weight: normal; letter-spacing: 0; margin-top: 2pt;">
                    {{ $venta->motivo_anulacion }}
                </div>
            @endif
        </div>
    @endif

    <div class="centro">
        <div class="tienda">{{ $tienda }}</div>
        <div class="titulo">RECIBO DE VENTA</div>
        <div class="codigo">{{ $venta->codigo }}</div>
    </div>

    <div class="separador"></div>

    <table class="datos">
        <tr>
            <td class="etiqueta">Fecha</td>
            <td class="derecha">{{ $venta->vendida_en?->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Cliente</td>
            <td class="derecha">{{ $venta->cliente?->persona?->nombre_completo ?? 'Público general' }}</td>
        </tr>
        @if ($venta->cliente?->persona?->carnet)
            <tr>
                <td class="etiqueta">Carnet</td>
                <td class="derecha">{{ $venta->cliente->persona->carnet }}</td>
            </tr>
        @endif
        <tr>
            <td class="etiqueta">Atendió</td>
            <td class="derecha">{{ $venta->user?->name ?? '—' }}</td>
        </tr>
    </table>

    <div class="separador"></div>

    <table class="lineas">
        @foreach ($venta->detalles as $detalle)
            @php
                $importe = (float) $detalle->precio_unitario - (float) $detalle->descuento;
            @endphp
            <tr>
                <td>{{ $detalle->producto?->nombre ?? 'Producto' }}</td>
                <td class="derecha fuerte" style="width: 34%">
                    {{ number_format($importe, 2, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="detalle">
                    {{ $detalle->unidad?->codigo_interno }}
                    @if ($detalle->unidad?->serial)
                        · S/N {{ $detalle->unidad->serial }}
                    @endif
                    @if ((float) $detalle->descuento > 0)
                        {{-- La rebaja se imprime: el cliente tiene que ver que
                             el precio de lista era otro. --}}
                        <br>
                        Precio {{ number_format((float) $detalle->precio_unitario, 2, ',', '.') }}
                        · Descuento −{{ number_format((float) $detalle->descuento, 2, ',', '.') }}
                    @endif
                    @if ($detalle->unidad?->garantia_hasta)
                        <br>Garantía hasta {{ $detalle->unidad->garantia_hasta->format('d/m/Y') }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <div class="separador"></div>

    <table class="totales">
        <tr>
            <td class="tenue">Subtotal</td>
            <td class="derecha">Bs {{ number_format((float) $venta->subtotal, 2, ',', '.') }}</td>
        </tr>
        @if ((float) $venta->descuento > 0)
            <tr>
                <td class="tenue">Descuentos</td>
                <td class="derecha">− Bs {{ number_format((float) $venta->descuento, 2, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="total-final">
            <td>TOTAL</td>
            <td class="derecha">Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="separador"></div>

    <table class="datos">
        <tr>
            <td class="etiqueta">Pago</td>
            <td class="derecha">{{ $metodosPago[$venta->metodo_pago] ?? $venta->metodo_pago }}</td>
        </tr>
        @if ($venta->metodo_pago === 'mixto')
            {{-- En el mixto el método por sí solo no dice cuánto entró por caja
                 y cuánto por el banco. --}}
            <tr>
                <td class="etiqueta">En efectivo</td>
                <td class="derecha">Bs {{ number_format((float) $venta->monto_efectivo, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="etiqueta">Por QR</td>
                <td class="derecha">Bs {{ number_format((float) $venta->monto_qr, 2, ',', '.') }}</td>
            </tr>
        @endif
        @if ($venta->qrCobro)
            <tr>
                <td class="etiqueta">QR</td>
                <td class="derecha">{{ $venta->qrCobro->nombre }}</td>
            </tr>
        @endif
    </table>

    @if ($venta->notas)
        <div class="separador"></div>
        <div class="tenue">{{ $venta->notas }}</div>
    @endif

    <div class="separador"></div>

    <div class="pie">
        {{ $venta->detalles->count() }}
        {{ $venta->detalles->count() === 1 ? 'aparato' : 'aparatos' }}
        · Emitido el {{ now()->format('d/m/Y H:i') }}
        <br>
        Conserva este recibo para cualquier reclamo de garantía.
        <br>
        ¡Gracias por su compra!
    </div>

</body>

</html>
