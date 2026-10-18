{{--
    Cabecera de marca para el ticket de 80 mm (recibo). Blanco y negro: sale
    por la impresora térmica del mostrador. Estilos en línea: DomPDF.

    Recibe $marca = MarcaDocumentos::datos($tienda).
--}}
<div style="text-align: center;">
    @if ($marca['logo'])
        <img src="{{ $marca['logo'] }}" alt="{{ $marca['nombre'] }}" style="width: 40mm; margin-bottom: 3pt;">
    @else
        <div style="font-size: 12pt; font-weight: bold; letter-spacing: .5pt;">{{ $marca['nombre'] }}</div>
        @if ($marca['lema'])
            <div style="font-size: 6.5pt; letter-spacing: 1pt; color: #555;">{{ mb_strtoupper($marca['lema']) }}</div>
        @endif
    @endif

    @if ($marca['sucursal'] || $marca['direccion'])
        <div style="font-size: 6.5pt; color: #333; margin-top: 2pt; line-height: 1.35;">
            @if ($marca['sucursal']) <strong>{{ $marca['sucursal'] }}</strong> @endif
            @if ($marca['sucursal'] && $marca['direccion']) · @endif
            {{ $marca['direccion'] }}
        </div>
    @endif

    @if ($marca['telefono'] || $marca['nit'])
        <div style="font-size: 6.5pt; color: #333; line-height: 1.35;">
            @if ($marca['telefono']) Tel. {{ $marca['telefono'] }} @endif
            @if ($marca['telefono'] && $marca['nit']) · @endif
            @if ($marca['nit']) NIT {{ $marca['nit'] }} @endif
        </div>
    @endif
</div>
