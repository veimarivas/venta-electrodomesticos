<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas · {{ $titulo }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">

    <style>
        /*
            Medidas en milímetros: una etiqueta impresa tiene que salir del
            tamaño real del adhesivo, y los píxeles dependen del DPI.
        */
        :root {
            --etiqueta-ancho: {{ ['pequena' => '50mm', 'mediana' => '70mm', 'grande' => '100mm'][$tamano] }};
            --etiqueta-alto: {{ ['pequena' => '25mm', 'mediana' => '35mm', 'grande' => '50mm'][$tamano] }};
        }

        body {
            background: #eef1f5;
            font-family: system-ui, "Segoe UI", sans-serif;
        }

        .barra-acciones {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            border-bottom: 1px solid #dfe3e8;
            box-shadow: 0 2px 10px rgba(20, 36, 61, .06);
        }

        .hoja {
            display: flex;
            flex-wrap: wrap;
            gap: 3mm;
            padding: 8mm;
            margin: 1.5rem auto;
            max-width: 220mm;
            background: #fff;
            box-shadow: 0 .5rem 2rem rgba(20, 36, 61, .12);
        }

        .etiqueta {
            width: var(--etiqueta-ancho);
            height: var(--etiqueta-alto);
            padding: 2mm;
            border: 1px dashed #c7ced8;
            border-radius: 1mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            /* Una etiqueta nunca debe partirse entre dos páginas */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .etiqueta-producto {
            font-size: {{ $tamano === 'pequena' ? '2.4mm' : '3mm' }};
            font-weight: 600;
            line-height: 1.2;
            /* Máximo dos líneas: los nombres largos se recortan */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .etiqueta-marca {
            font-size: {{ $tamano === 'pequena' ? '2mm' : '2.4mm' }};
            color: #6b778a;
        }

        /*
            El alto del código se fija en milímetros, no se deja al flujo: un
            Code128 bajo se lee mal de pie y con el aparato en la mano, porque
            el lector necesita cruzar todas las barras en una sola pasada.

            El ancho es el 100% de la etiqueta a propósito. El SVG lleva
            viewBox (ver GeneradorEtiquetas), así que ESCALA: cuanto más ancho,
            más gruesa la barra fina y más fácil la lectura. Las zonas mudas
            que exige la norma ya van dentro del viewBox, así que el código
            nunca queda pegado al borde aunque ocupe todo el ancho.
        */
        .etiqueta-codigo-svg {
            display: block;
            flex: 0 0 auto;
            height: {{ ['pequena' => '7mm', 'mediana' => '11mm', 'grande' => '16mm'][$tamano] }};
        }

        .etiqueta-codigo-svg svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .etiqueta-codigo-texto {
            text-align: center;
            font-family: ui-monospace, "Consolas", monospace;
            font-size: {{ $tamano === 'pequena' ? '2.2mm' : '2.8mm' }};
            letter-spacing: .02em;
        }

        .etiqueta-pie {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1mm;
        }

        .etiqueta-precio {
            font-size: {{ $tamano === 'pequena' ? '3.2mm' : '4.4mm' }};
            font-weight: 700;
            white-space: nowrap;
        }

        .etiqueta-serial {
            font-size: 2mm;
            color: #6b778a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ---------- Impresión ---------- */
        @media print {
            @page {
                size: A4;
                margin: 6mm;
            }

            body {
                background: #fff;
            }

            /* Los controles no se imprimen */
            .barra-acciones,
            .sin-imprimir {
                display: none !important;
            }

            .hoja {
                margin: 0;
                padding: 0;
                max-width: none;
                box-shadow: none;
                gap: 2mm;
            }

            /* Sin el borde punteado de guía: al imprimir sobre adhesivo
               precortado, ese borde ensucia la etiqueta */
            .etiqueta {
                border: none;
            }
        }
    </style>
</head>

<body>

    {{-- ===================== Controles (no se imprimen) ===================== --}}
    <div class="barra-acciones py-3 px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-0">Etiquetas · {{ $titulo }}</h5>
                <small class="text-muted">
                    {{ $etiquetas->count() }}
                    {{ $etiquetas->count() === 1 ? 'etiqueta' : 'etiquetas' }}
                    @if ($copias > 1)
                        ({{ $copias }} copias por unidad)
                    @endif
                </small>
            </div>

            <form method="GET" class="d-flex flex-wrap align-items-end gap-2">
                {{-- Se conservan los parámetros de origen (ids de la selección) --}}
                @foreach (request()->except(['tamano', 'copias', 'imprimir']) as $clave => $valor)
                    <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                @endforeach

                <div>
                    <label for="tamano" class="form-label mb-1 fs-12">Tamaño</label>
                    <select name="tamano" id="tamano" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($tamanos as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected($tamano === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="copias" class="form-label mb-1 fs-12">Copias por unidad</label>
                    <select name="copias" id="copias" class="form-select form-select-sm" onchange="this.form.submit()">
                        @for ($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" @selected($copias === $i)>{{ $i }}</option>
                        @endfor
                    </select>
                </div>

                <button type="button" class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="ri-printer-line align-bottom me-1"></i> Imprimir
                </button>

                <button type="button" class="btn btn-sm btn-light" onclick="window.close()">
                    Cerrar
                </button>
            </form>
        </div>

        <div class="alert alert-info alert-borderless mt-3 mb-0 py-2 fs-13">
            <i class="ri-information-line align-bottom me-1"></i>
            En el diálogo de impresión, desactiva <strong>encabezados y pies de página</strong> y pon los
            márgenes en <strong>ninguno</strong> para que las etiquetas salgan del tamaño exacto.
        </div>
    </div>

    {{-- ===================== Hoja de etiquetas ===================== --}}
    <div class="hoja">
        @forelse ($etiquetas as $etiqueta)
            @php $unidad = $etiqueta['unidad']; @endphp

            <div class="etiqueta">
                <div>
                    <div class="etiqueta-producto">{{ $unidad->producto->nombre }}</div>

                </div>

                <div class="etiqueta-codigo-svg">
                    {!! $etiqueta['svg'] !!}
                </div>

                <div>
                    <div class="etiqueta-codigo-texto">{{ $unidad->codigo_interno }}</div>

                    <div class="etiqueta-pie">
                        <span class="etiqueta-serial">
                            @if ($unidad->serial)
                                S/N {{ $unidad->serial }}
                            @endif
                        </span>
                        <span class="etiqueta-precio">Bs {{ number_format((float) $unidad->precio_venta, 2) }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-5 w-100 sin-imprimir">
                No hay unidades que etiquetar.
            </div>
        @endforelse
    </div>

    <script>
        // Al llegar con ?imprimir=1 se abre el diálogo solo, para que el flujo
        // desde "Recepcionar" sea de un clic.
        @if ($autoImprimir)
            window.addEventListener('load', () => window.print());
        @endif
    </script>
</body>

</html>
