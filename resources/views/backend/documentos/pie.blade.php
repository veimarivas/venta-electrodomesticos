{{--
    Pie fijo de los documentos en hoja: la marca, el lema y cuándo se emitió.
    `position: fixed` en DomPDF lo repite en cada página.

    Recibe $marca = MarcaDocumentos::datos($tienda).
--}}
<div style="position: fixed; bottom: -16pt; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #8a94a3; border-top: .5pt solid #d6dbe2; padding-top: 3pt;">
    {{ $marca['nombre'] }}@if ($marca['lema']) · {{ $marca['lema'] }}@endif
    · Emitido el {{ now()->format('d/m/Y H:i') }}
</div>
