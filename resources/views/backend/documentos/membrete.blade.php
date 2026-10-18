{{--
    Membrete de los documentos en hoja (estado de cuenta, orden de servicio,
    asistencia): logo a la izquierda, título y número a la derecha, datos de
    contacto, y una raya dorada —el oro del logo— que cierra la cabecera.

    DomPDF: tablas y estilos en línea (CSS 2.1, sin flex ni grid).

    Recibe:
      $marca  = MarcaDocumentos::datos($tienda)
      $titulo = «ESTADO DE CUENTA»…
      $codigo = el número del documento (opcional)
--}}
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="width: 45%; vertical-align: middle; padding: 0; border: 0;">
            @if ($marca['logo'])
                <img src="{{ $marca['logo'] }}" alt="{{ $marca['nombre'] }}" style="height: 58pt;">
            @else
                <div style="font-size: 17pt; font-weight: bold; color: #10233c;">{{ $marca['nombre'] }}</div>
                @if ($marca['lema'])
                    <div style="font-size: 7pt; letter-spacing: 1.5pt; color: #8a6a1f;">{{ mb_strtoupper($marca['lema']) }}</div>
                @endif
            @endif
        </td>
        <td style="vertical-align: middle; text-align: right; padding: 0; border: 0;">
            <div style="font-size: 12pt; font-weight: bold; letter-spacing: 1.5pt; color: #10233c;">{{ $titulo }}</div>
            @if (! empty($codigo))
                <div style="font-size: 11pt; font-weight: bold; margin-top: 2pt;">{{ $codigo }}</div>
            @endif
            <div style="font-size: 7pt; color: #555; margin-top: 5pt; line-height: 1.45;">
                <strong style="color: #10233c;">{{ $marca['nombre'] }}</strong>@if ($marca['sucursal']) · {{ $marca['sucursal'] }}@endif
                @if ($marca['direccion'])<br>{{ $marca['direccion'] }}@endif
                @if ($marca['telefono'] || $marca['nit'])
                    <br>
                    @if ($marca['telefono']) Tel. {{ $marca['telefono'] }} @endif
                    @if ($marca['telefono'] && $marca['nit']) · @endif
                    @if ($marca['nit']) NIT {{ $marca['nit'] }} @endif
                @endif
            </div>
        </td>
    </tr>
</table>
<div style="height: 0; border-top: 2pt solid #b8892d; margin: 8pt 0 12pt;"></div>
