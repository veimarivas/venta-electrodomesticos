<div class="items-modulo pos-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-store-2-line me-1"></i> Ventas · Punto de venta
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title rounded-3 fs-3"
                                      style="background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24);">
                                    <i class="ri-shopping-cart-2-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Punto de venta</h4>
                                <p class="mb-0" style="color: rgba(255,255,255,.65);">
                                    Escanea el serial, ajusta el precio pactado y cobra. El stock se descuenta solo.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="pos-hero-resumen">
                            <div class="pos-hero-dato">
                                <span class="pos-hero-etiqueta">Venta</span>
                                <span class="pos-hero-valor font-monospace">{{ $this->codigoPrevisto }}</span>
                            </div>
                            <div class="pos-hero-dato">
                                <span class="pos-hero-etiqueta">Aparatos</span>
                                <span class="pos-hero-valor">{{ count($carrito) }}</span>
                            </div>
                            <div class="pos-hero-dato">
                                <span class="pos-hero-etiqueta">Total</span>
                                <span class="pos-hero-valor">
                                    Bs {{ number_format($this->totalEnCentavos / 100, 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Columna izquierda: buscar y carrito ===================== --}}
        <div class="col-xl-8">
            {{-- ---------- Buscador ---------- --}}
            <div class="card border-0 shadow-sm mb-4 pos-buscador">
                <div class="card-body p-4">
                    <label for="v-buscar" class="form-label fw-semibold" style="color: var(--marca-tinta);">
                        <i class="ri-barcode-line align-bottom me-1" style="color: var(--marca-azul-texto);"></i>
                        Buscar aparato por serial o código interno
                    </label>
                    <div class="search-box">
                        <input type="text" id="v-buscar" class="form-control form-control-lg crud-busqueda pos-input-buscar"
                            placeholder="Escanea o escribe el serial, el código interno, el SKU o el nombre..."
                            wire:model.live.debounce.300ms="buscar" autofocus>
                        <i class="ri-search-line search-icon"></i>
                    </div>

                    @if ($this->coincidencias->isNotEmpty())
                        <div class="linea-productos-lista mt-3">
                            @foreach ($this->coincidencias as $unidad)
                                @php
                                    $vendible = $unidad->esVendible();
                                    $venta = $unidad->ventaDetalle?->venta;
                                @endphp

                                {{-- Los no vendibles también se listan: con la etiqueta
                                     de un aparato ya vendido delante, «sin resultados»
                                     no dice si se tecleó mal o si salió esta mañana. --}}
                                <button type="button"
                                    class="linea-producto-opcion @if (! $vendible) pos-resultado-bloqueado @endif"
                                    wire:key="unidad-{{ $unidad->id }}"
                                    @if ($vendible) wire:click="agregar({{ $unidad->id }})" @else disabled @endif>
                                    <span class="min-w-0 flex-grow-1 text-start">
                                        <span class="d-block fw-semibold text-truncate">
                                            {{ $unidad->producto?->nombre ?? 'Producto' }}
                                        </span>
                                        <span class="d-block text-muted fs-12 text-truncate">
                                            <code>{{ $unidad->codigo_interno }}</code>
                                            @if ($unidad->serial) · Serial {{ $unidad->serial }} @endif
                                            @if ($unidad->producto?->marca) · {{ $unidad->producto->marca->nombre }} @endif
                                            @if ($unidad->producto?->sku) · SKU {{ $unidad->producto->sku }} @endif
                                        </span>

                                        @unless ($vendible)
                                            <span class="pos-resultado-estado">
                                                <i class="ri-error-warning-line"></i>
                                                @if ($unidad->estado === 'vendido' && $venta)
                                                    Vendido el {{ $venta->vendida_en?->format('d/m/Y') }}
                                                    en la venta {{ $venta->codigo }}
                                                @else
                                                    No se puede vender:
                                                    {{ \App\Models\Unidad::ESTADOS[$unidad->estado] ?? $unidad->estado }}
                                                @endif
                                            </span>
                                        @endunless
                                    </span>
                                    <span class="text-end flex-shrink-0">
                                        <span class="d-block fw-semibold fs-13">
                                            Bs {{ number_format((float) $unidad->precio_venta, 2, ',', '.') }}
                                        </span>
                                        @if ($vendible && (float) ($unidad->producto?->descuento_maximo ?? 0) > 0)
                                            <span class="d-block text-muted fs-11">
                                                Rebaja hasta Bs
                                                {{ number_format((float) $unidad->producto->descuento_maximo, 2, ',', '.') }}
                                            </span>
                                        @endif
                                    </span>
                                    <span class="flex-shrink-0" style="color: {{ $vendible ? 'var(--marca-azul)' : 'var(--marca-apagado)' }};">
                                        <i class="{{ $vendible ? 'ri-add-circle-line' : 'ri-forbid-2-line' }} fs-18"></i>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @elseif ($this->busquedaSinResultados)
                        <div class="pos-busqueda-vacia mt-3">
                            <i class="ri-search-eye-line"></i>
                            <div>
                                <strong class="d-block">No existe ningún aparato con «{{ $buscar }}».</strong>
                                <span class="fs-12">
                                    Revisa el código o el serial. Si el aparato es nuevo, tiene que
                                    entrar por una compra recepcionada.
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ---------- Carrito ---------- --}}
            <div class="card border-0 shadow-sm pos-carrito-card">
                <div class="pos-carrito-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="pos-carrito-header-icon">
                            <i class="ri-shopping-cart-2-line"></i>
                        </span>
                        <h5 class="card-title mb-0 pos-carrito-titulo">Carrito</h5>
                        @if (count($carrito) > 0)
                            <span class="pos-carrito-badge">{{ count($carrito) }}</span>
                        @endif
                    </div>
                    @if ($carrito !== [])
                        <button type="button" class="pos-carrito-vaciar-btn"
                            wire:click="confirmarVaciar">
                            <i class="ri-delete-bin-line"></i> Vaciar
                        </button>
                    @endif
                </div>

                <div class="card-body p-0">
                    @if ($carrito === [])
                        <div class="pos-carrito-vacio">
                            <div class="pos-carrito-vacio-icono">
                                <i class="ri-shopping-cart-line"></i>
                            </div>
                            <h6 class="pos-carrito-vacio-titulo">Carrito vacío</h6>
                            <p class="pos-carrito-vacio-texto mb-0">
                                Escanea el primer aparato para empezar la venta.
                            </p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 tabla-lineas-compra pos-tabla-carrito">
                                <thead>
                                    <tr class="text-uppercase fs-11 text-muted">
                                        <th scope="col" class="ps-4">Aparato</th>
                                        <th scope="col" class="text-end" style="width: 8.5rem">Referencia</th>
                                        <th scope="col" style="width: 12.5rem">Precio a cobrar</th>
                                        <th scope="col" class="text-end" style="width: 8rem">Descuento</th>
                                        <th scope="col" style="width: 3rem"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($carrito as $indice => $linea)
                                        @php
                                            $u = $this->unidadesDelCarrito[$linea['unidad_id']] ?? null;
                                            $lista = (float) $linea['precio_lista'];
                                            $tope = (float) $linea['tope_descuento'];
                                            $descuentoCentavos = $this->descuentoDeLinea($linea);
                                            $descuento = $descuentoCentavos / 100;
                                            $topeCentavos = (int) round($tope * 100);
                                            $minimo = max($lista - $tope, 0);
                                        @endphp
                                        <tr wire:key="carrito-{{ $indice }}-{{ $linea['unidad_id'] }}">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="pos-carrito-item-num">{{ $indice + 1 }}</span>
                                                    <div class="min-w-0">
                                                        <div class="fw-semibold" style="color: var(--marca-tinta);">
                                                            {{ $u?->producto?->nombre ?? 'Producto' }}
                                                        </div>

                                                        {{-- Ficha completa del aparato: el cajero tiene
                                                             que poder comprobar, sin salir del carrito,
                                                             que es el que tiene en la mano. --}}
                                                        <div class="pos-carrito-ficha">
                                                            <span class="pos-carrito-dato">
                                                                <i class="ri-barcode-line"></i>
                                                                <code>{{ $u?->codigo_interno }}</code>
                                                            </span>
                                                            @if ($u?->serial)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-fingerprint-line"></i>
                                                                    S/N {{ $u->serial }}
                                                                </span>
                                                            @endif
                                                            @if ($u?->producto?->marca)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-store-2-line"></i>
                                                                    {{ $u->producto->marca->nombre }}
                                                                </span>
                                                            @endif
                                                            @if ($u?->producto?->modelo)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-price-tag-line"></i>
                                                                    {{ $u->producto->modelo }}
                                                                </span>
                                                            @endif
                                                            @if ($u?->producto?->sku)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-hashtag"></i>
                                                                    {{ $u->producto->sku }}
                                                                </span>
                                                            @endif
                                                            @if ($u?->producto?->categoria)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-folder-2-line"></i>
                                                                    {{ $u->producto->categoria->nombre }}
                                                                </span>
                                                            @endif
                                                            @if ($u?->garantia_hasta)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-shield-check-line"></i>
                                                                    Garantía {{ $u->garantia_hasta->format('d/m/Y') }}
                                                                </span>
                                                            @endif
                                                            @if ($u?->ubicacion)
                                                                <span class="pos-carrito-dato">
                                                                    <i class="ri-map-pin-line"></i>
                                                                    {{ $u->ubicacion }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="text-end">
                                                <span class="pos-precio-referencia">
                                                    Bs {{ number_format($lista, 2, ',', '.') }}
                                                </span>
                                                <span class="d-block fs-11 text-muted">
                                                    @if ($tope > 0)
                                                        Mín. Bs {{ number_format($minimo, 2, ',', '.') }}
                                                    @else
                                                        Sin descuento
                                                    @endif
                                                </span>
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="pos-carrito-input-precio-span px-2 py-1">Bs</span>
                                                    <input type="number" step="0.01" min="0.01" max="{{ $lista }}"
                                                        wire:model.live.debounce.500ms="carrito.{{ $indice }}.precio"
                                                        class="form-control text-end pos-carrito-input-precio @error('carrito.'.$indice.'.precio') is-invalid @enderror"
                                                        style="max-width: 9rem;"
                                                        aria-label="Precio a cobrar de {{ $u?->codigo_interno }}">
                                                </div>

                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    @if ($descuento > 0)
                                                        <button type="button" class="pos-carrito-accion-desc"
                                                            wire:click="quitarDescuento({{ $indice }})">
                                                            <i class="ri-refresh-line"></i> Precio de lista
                                                        </button>
                                                    @endif
                                                    @if ($topeCentavos > 0 && $descuentoCentavos < $topeCentavos)
                                                        <button type="button" class="pos-carrito-accion-desc pos-carrito-accion-max"
                                                            wire:click="aplicarDescuentoMaximo({{ $indice }})">
                                                            <i class="ri-price-tag-3-line"></i> Rebaja máxima
                                                        </button>
                                                    @endif
                                                </div>

                                                @error('carrito.'.$indice.'.precio')
                                                    <small class="text-danger fs-11 d-block mt-1">{{ $message }}</small>
                                                @enderror
                                            </td>

                                            <td class="text-end">
                                                @if ($descuento > 0)
                                                    <span class="pos-descuento-badge">
                                                        − Bs {{ number_format($descuento, 2, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted fs-12">—</span>
                                                @endif
                                            </td>

                                            <td class="text-end pe-3">
                                                <button type="button" class="pos-carrito-quitar"
                                                    wire:click="confirmarQuitar({{ $indice }})"
                                                    title="Quitar del carrito"
                                                    aria-label="Quitar {{ $u?->codigo_interno }}">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="pos-carrito-footer">
                            <i class="ri-information-line align-bottom me-1"></i>
                            La <strong style="color: var(--marca-tinta);">referencia</strong> es el precio de lista del aparato. Lo que se teclea es el
                            precio pactado con el cliente; la diferencia queda registrada como descuento.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===================== Columna derecha: cobro ===================== --}}
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm pos-cobro">
                <div class="pos-cobro-header">
                    <span class="pos-cobro-header-icon">
                        <i class="ri-cash-line"></i>
                    </span>
                    <h5 class="card-title mb-0 pos-cobro-titulo">Cobro</h5>
                </div>

                <div class="card-body">
                    {{-- ---------- Cliente ---------- --}}
                    <div class="pos-seccion">
                        <label class="pos-seccion-label">
                            <i class="ri-user-3-line"></i> Cliente <span class="text-muted fw-normal fs-12">(opcional)</span>
                        </label>

                        @if ($this->clienteElegido)
                            <div class="crud-categoria-fijada">
                                <span class="crud-categoria-fijada-icono"><i class="ri-user-heart-line"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <span class="fw-semibold d-block text-truncate">
                                        {{ $this->clienteElegido->persona->nombre_completo }}
                                    </span>
                                    <small class="text-muted">{{ $this->clienteElegido->codigo }}</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-ghost-danger btn-icon flex-shrink-0"
                                    wire:click="quitarCliente" title="Quitar cliente">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        @else
                            <div class="search-box mb-2">
                                <input type="text" class="form-control form-control-sm crud-busqueda"
                                    placeholder="Buscar por nombre, carnet o código..."
                                    wire:model.live.debounce.350ms="buscarCliente">
                                <i class="ri-search-line search-icon"></i>
                            </div>

                            @if ($this->clientesEncontrados->isNotEmpty())
                                <div class="linea-productos-lista mb-2">
                                    @foreach ($this->clientesEncontrados as $cliente)
                                        <button type="button" class="linea-producto-opcion"
                                            wire:key="cliente-{{ $cliente->id }}"
                                            wire:click="elegirCliente({{ $cliente->id }})">
                                            <span class="min-w-0 flex-grow-1 text-start">
                                                <span class="d-block fw-semibold text-truncate">
                                                    {{ $cliente->persona->nombre_completo }}
                                                </span>
                                                <span class="d-block text-muted fs-12">
                                                    {{ $cliente->codigo }} · CI {{ $cliente->persona->carnet }}
                                                </span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif ($this->personasEncontradas->isNotEmpty())
                                {{-- Segundo peldaño: no es cliente, pero la persona ya
                                     está en el sistema (trabaja aquí, o alguien la
                                     registró antes). Se le abre la ficha con sus datos
                                     en vez de teclearlos otra vez. --}}
                                <div class="pos-aviso pos-aviso-info mb-2">
                                    <i class="ri-user-search-line"></i>
                                    <span>
                                        No es cliente todavía, pero ya está registrada como persona.
                                    </span>
                                </div>

                                <div class="linea-productos-lista mb-2">
                                    @foreach ($this->personasEncontradas as $persona)
                                        <button type="button" class="linea-producto-opcion"
                                            wire:key="persona-{{ $persona->id }}"
                                            wire:click="registrarPersonaComoCliente({{ $persona->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="registrarPersonaComoCliente">
                                            <span class="min-w-0 flex-grow-1 text-start">
                                                <span class="d-block fw-semibold text-truncate">
                                                    {{ $persona->nombre_completo }}
                                                </span>
                                                <span class="d-block text-muted fs-12">
                                                    CI {{ $persona->carnet }}
                                                    @if ($persona->celular) · {{ $persona->celular }} @endif
                                                </span>
                                            </span>
                                            <span class="badge bg-primary-subtle text-primary flex-shrink-0">
                                                <i class="ri-user-add-line align-bottom me-1"></i> Usar
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif ($this->clienteSinResultados)
                                <div class="pos-aviso pos-aviso-info mb-2">
                                    <i class="ri-user-search-line"></i>
                                    <span>Nadie coincide con «{{ $buscarCliente }}», ni en clientes ni en personas.</span>
                                </div>
                            @endif

                            {{-- El alta solo se ofrece cuando ya se buscó y no apareció
                                 nadie: sin esa condición, la prisa del mostrador acabaría
                                 creando una ficha nueva para alguien que ya está. --}}
                            @if ($puedeCrearClientes && $this->clienteSinResultados)
                                <button type="button" class="pos-alta-cliente w-100 mb-3"
                                    wire:click="abrirNuevoCliente">
                                    <span class="pos-alta-cliente-icono">
                                        <i class="ri-user-add-line"></i>
                                    </span>
                                    <span class="min-w-0 text-start">
                                        <span class="pos-alta-cliente-titulo">Registrar nuevo cliente</span>
                                        <span class="pos-alta-cliente-nota">
                                            Se da de alta sin salir de la venta
                                        </span>
                                    </span>
                                    <i class="ri-arrow-right-line pos-alta-cliente-flecha"></i>
                                </button>
                            @elseif (! $this->buscandoCliente)
                                <div class="form-text fs-11 mb-3">
                                    Vender sin cliente es lo normal en mostrador. Si te lo pide,
                                    búscalo por carnet o nombre.
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- ---------- Método de pago ---------- --}}
                    <div class="pos-seccion">
                        <label class="pos-seccion-label">
                            <i class="ri-bank-card-line"></i> Método de pago <span class="text-danger">*</span>
                        </label>

                        {{-- Solo lo que el mostrador cobra hoy. Tarjeta y transferencia
                             siguen existiendo en el histórico, pero ya no se ofrecen. --}}
                        <div class="pos-metodos" role="radiogroup" aria-label="Método de pago">
                            @php
                                $iconosPago = [
                                    'efectivo' => 'ri-money-dollar-box-line',
                                    'qr' => 'ri-qr-code-line',
                                    'mixto' => 'ri-split-cells-horizontal',
                                ];
                            @endphp
                            @foreach ($metodosPos as $valor)
                                <button type="button" wire:key="metodo-{{ $valor }}"
                                    class="pos-metodo @if ($metodoPago === $valor) esta-activo @endif"
                                    role="radio" aria-checked="{{ $metodoPago === $valor ? 'true' : 'false' }}"
                                    wire:click="$set('metodoPago', '{{ $valor }}')">
                                    <i class="{{ $iconosPago[$valor] }}"></i>
                                    <span>{{ $valor === 'mixto' ? 'Mixto' : $metodosPago[$valor] }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('metodoPago') <div class="text-danger fs-12 mt-2">{{ $message }}</div> @enderror
                    </div>

                    {{-- ---------- Cobro por QR ---------- --}}
                    @if ($this->pagoUsaQr)
                        <div class="pos-seccion pos-qr-panel">
                            @if ($this->qrsVigentes->isEmpty())
                                <div class="pos-aviso pos-aviso-error">
                                    <i class="ri-error-warning-line"></i>
                                    <span>
                                        No hay ningún QR vigente registrado. Registra uno en
                                        <strong>Ventas › QR de cobro</strong> o cobra en efectivo.
                                    </span>
                                </div>
                            @else
                                @if ($this->qrsVigentes->count() > 1)
                                    <label for="v-qr" class="form-label fs-12 text-muted mb-1">QR a mostrar</label>
                                    <select id="v-qr" wire:model.live="qrCobroId" class="form-select form-select-sm mb-2">
                                        @foreach ($this->qrsVigentes as $qr)
                                            <option value="{{ $qr->id }}">
                                                {{ $qr->nombre }}@if ($qr->banco) · {{ $qr->banco }} @endif
                                            </option>
                                        @endforeach
                                    </select>
                                @endif

                                @if ($this->qrElegido)
                                    <figure class="pos-qr-imagen mb-2">
                                        <img src="{{ $this->qrElegido->imagen_url }}"
                                            alt="QR de cobro {{ $this->qrElegido->nombre }}">
                                        <figcaption>
                                            <strong class="d-block">{{ $this->qrElegido->nombre }}</strong>
                                            @if ($this->qrElegido->titular)
                                                <span class="d-block text-muted fs-12">{{ $this->qrElegido->titular }}</span>
                                            @endif
                                            <span class="badge {{ $this->qrElegido->dias_restantes <= 7 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }} mt-1">
                                                <i class="ri-calendar-check-line align-bottom me-1"></i>
                                                Válido hasta {{ $this->qrElegido->fecha_limite->format('d/m/Y') }}
                                            </span>
                                        </figcaption>
                                    </figure>
                                @endif
                                @error('qrCobroId') <div class="text-danger fs-12 mb-2">{{ $message }}</div> @enderror

                                {{-- Reparto del pago mixto --}}
                                @if ($metodoPago === 'mixto')
                                    @php $mixtoSinRepartir = trim($montoEfectivo) === '' && trim($montoQr) === ''; @endphp

                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label for="v-efectivo" class="form-label fs-12 text-muted mb-1">
                                                <i class="ri-money-dollar-box-line align-bottom"></i> En efectivo
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Bs</span>
                                                <input type="number" step="0.01" min="0"
                                                    max="{{ $this->totalEnCentavos / 100 }}" id="v-efectivo"
                                                    class="form-control text-end" placeholder="0.00"
                                                    wire:model.live.debounce.500ms="montoEfectivo">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label for="v-monto-qr" class="form-label fs-12 text-muted mb-1">
                                                <i class="ri-qr-code-line align-bottom"></i> Por QR
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Bs</span>
                                                <input type="number" step="0.01" min="0"
                                                    max="{{ $this->totalEnCentavos / 100 }}" id="v-monto-qr"
                                                    class="form-control text-end" placeholder="0.00"
                                                    wire:model.live.debounce.500ms="montoQr">
                                            </div>
                                        </div>
                                    </div>

                                    @if ($mixtoSinRepartir)
                                        <div class="pos-aviso pos-aviso-info mb-2">
                                            <i class="ri-keyboard-line"></i>
                                            <span>
                                                Escribe cuánto paga en efectivo <em>o</em> cuánto por QR: el otro
                                                campo se completa con la diferencia.
                                            </span>
                                        </div>
                                    @elseif ($this->diferenciaMixtoEnCentavos !== 0)
                                        <div class="pos-aviso pos-aviso-error mb-2">
                                            <i class="ri-scales-3-line"></i>
                                            <span>
                                                @if ($this->diferenciaMixtoEnCentavos > 0)
                                                    Faltan Bs
                                                    {{ number_format($this->diferenciaMixtoEnCentavos / 100, 2, ',', '.') }}
                                                    por repartir.
                                                @else
                                                    El reparto supera el total en Bs
                                                    {{ number_format(abs($this->diferenciaMixtoEnCentavos) / 100, 2, ',', '.') }}.
                                                @endif
                                            </span>
                                        </div>
                                    @else
                                        <div class="pos-aviso pos-aviso-ok mb-2">
                                            <i class="ri-check-line"></i>
                                            <span>El reparto cuadra con el total a cobrar.</span>
                                        </div>
                                    @endif
                                @endif

                                {{-- Respaldo del pago --}}
                                <label for="v-comprobante" class="form-label fs-12 text-muted mb-1">
                                    Respaldo del pago <span class="text-danger">*</span>
                                </label>

                                @if ($comprobante)
                                    <div class="pos-comprobante">
                                        <img src="{{ $comprobante->temporaryUrl() }}" alt="Respaldo del pago por QR">
                                        <div class="min-w-0 flex-grow-1">
                                            <span class="d-block fw-semibold fs-13 text-truncate">Comprobante listo</span>
                                            <span class="d-block text-muted fs-11 text-truncate">
                                                {{ $comprobante->getClientOriginalName() }}
                                            </span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-ghost-danger btn-icon flex-shrink-0"
                                            wire:click="quitarComprobante" title="Quitar el respaldo">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                @else
                                    <input type="file" id="v-comprobante" accept="image/*" capture="environment"
                                        class="form-control form-control-sm @error('comprobante') is-invalid @enderror"
                                        wire:model="comprobante">
                                    <div wire:loading wire:target="comprobante" class="text-muted fs-11 mt-1">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Subiendo el comprobante...
                                    </div>
                                    <div class="form-text fs-11">
                                        Foto o captura de la transferencia. Se guarda junto a la venta.
                                    </div>
                                @endif
                                @error('comprobante') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    @endif

                    {{-- ---------- Notas ---------- --}}
                    <div class="pos-seccion">
                        <label for="v-notas" class="pos-seccion-label">
                            <i class="ri-file-text-line"></i> Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                        </label>
                        <textarea id="v-notas" rows="2" wire:model.live.debounce.400ms="notas"
                            class="form-control @error('notas') is-invalid @enderror"
                            placeholder="Observaciones de la venta..."></textarea>
                    </div>

                    {{-- ---------- Totales ---------- --}}
                    <div class="pos-totales">
                        <div class="pos-total-linea">
                            <span>Subtotal (precios de referencia)</span>
                            <strong>Bs {{ number_format($this->subtotalEnCentavos / 100, 2, ',', '.') }}</strong>
                        </div>
                        <div class="pos-total-linea">
                            <span>Descuentos</span>
                            <strong class="pos-descuento-valor">
                                − Bs {{ number_format($this->descuentoEnCentavos / 100, 2, ',', '.') }}
                            </strong>
                        </div>
                        <div class="pos-total-linea pos-total-final">
                            <span>Total a cobrar</span>
                            <strong>Bs {{ number_format($this->totalEnCentavos / 100, 2, ',', '.') }}</strong>
                        </div>

                        @if ($metodoPago === 'mixto' && $carrito !== [])
                            <div class="pos-total-linea pos-total-reparto">
                                <span><i class="ri-money-dollar-box-line align-bottom me-1"></i>Efectivo</span>
                                <strong>Bs {{ number_format((float) ($montoEfectivo ?: 0), 2, ',', '.') }}</strong>
                            </div>
                            <div class="pos-total-linea">
                                <span><i class="ri-qr-code-line align-bottom me-1"></i>Por QR</span>
                                <strong>Bs {{ number_format((float) ($montoQr ?: 0), 2, ',', '.') }}</strong>
                            </div>
                        @endif

                        @if ($puedeVerCostos && $carrito !== [])
                            <div class="pos-total-linea pos-total-ganancia">
                                <span>Ganancia estimada</span>
                                <strong>Bs {{ number_format($this->gananciaEnCentavos / 100, 2, ',', '.') }}</strong>
                            </div>
                        @endif
                    </div>

                    @error('carrito')
                        <div class="text-danger fs-12 mt-2">{{ $message }}</div>
                    @enderror

                    {{-- ---------- Botón cobrar ---------- --}}
                    {{-- Abre el repaso, no cobra: registrar la venta solo se
                         deshace anulándola, y la anulación deja su rastro. --}}
                    <button type="button" class="btn w-100 mt-3 btn-lg pos-cobrar"
                        wire:click="confirmarCobro" @disabled(! $this->ventaValida)
                        wire:loading.attr="disabled" wire:target="confirmarCobro">
                        <span wire:loading.remove wire:target="confirmarCobro">
                            <i class="ri-check-double-line align-bottom me-1"></i> Cobrar y registrar
                        </span>
                        <span wire:loading wire:target="confirmarCobro">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Revisando la venta...
                        </span>
                    </button>

                    <p class="text-muted fs-12 mb-0 mt-2 text-center">
                        Antes de guardar verás un resumen para confirmar.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Alta rápida de cliente ===================== --}}
    <div class="modal fade zoomIn" id="modalClientePos" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content pos-modal-cliente">
                <div class="modal-header modal-crud-header pos-modal-cliente-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="pos-modal-cliente-icono">
                            <i class="ri-user-add-line"></i>
                        </span>
                        <div>
                            <h5 class="modal-title text-white mb-0">Nuevo cliente</h5>
                            <small style="color: rgba(255,255,255,.6);">Registrar y usar en esta venta</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit.prevent="guardarCliente">
                    <div class="modal-body p-4">
                        <p class="fs-13 mb-3 pos-modal-hint">
                            Lo mínimo para emitir el comprobante. La ficha completa se puede ampliar después
                            desde el módulo de clientes.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="pc-carnet" class="form-label">Carnet <span class="text-danger">*</span></label>
                                <input type="text" id="pc-carnet" inputmode="numeric"
                                    class="form-control @error('nuevoCarnet') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="nuevoCarnet" placeholder="Ej. 8765432">
                                @error('nuevoCarnet') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="pc-celular" class="form-label">
                                    Celular <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <input type="text" id="pc-celular" inputmode="numeric"
                                    class="form-control @error('nuevoCelular') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="nuevoCelular" placeholder="Ej. 71234567">
                                @error('nuevoCelular') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="pc-nombres" class="form-label">Nombres <span class="text-danger">*</span></label>
                                <input type="text" id="pc-nombres"
                                    class="form-control @error('nuevoNombres') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="nuevoNombres">
                                @error('nuevoNombres') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="pc-paterno" class="form-label">Apellido paterno</label>
                                <input type="text" id="pc-paterno"
                                    class="form-control @error('nuevoApellidoPaterno') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="nuevoApellidoPaterno">
                                @error('nuevoApellidoPaterno') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="pc-materno" class="form-label">Apellido materno</label>
                                <input type="text" id="pc-materno"
                                    class="form-control @error('nuevoApellidoMaterno') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="nuevoApellidoMaterno">
                                @error('nuevoApellidoMaterno') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn pos-modal-cliente-cancelar" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn pos-modal-cliente-guardar" @disabled(! $this->clienteNuevoValido)">
                            <i class="ri-save-line align-bottom me-1"></i> Registrar y usar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== Comprobante de la venta ===================== --}}
    <div class="modal fade zoomIn" id="modalVentaRegistrada" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content pos-modal-exito">
                <div class="modal-body p-4">
                    @if ($this->ventaRegistrada)
                        @php $venta = $this->ventaRegistrada; @endphp

                        <div class="text-center mb-4">
                            <div class="pos-modal-exito-icono mb-3">
                                <i class="ri-check-double-line"></i>
                            </div>
                            <h5 class="pos-modal-exito-titulo mb-1">Venta registrada</h5>
                            <p class="pos-modal-exito-codigo mb-0">{{ $venta->codigo }}</p>
                        </div>

                        <div class="pos-modal-exito-datos mb-3">
                            <div class="pos-modal-exito-dato">
                                <span class="pos-modal-exito-dato-etiqueta">Cliente</span>
                                <span class="pos-modal-exito-dato-valor">{{ $venta->cliente?->persona?->nombre_completo ?? 'Público general' }}</span>
                            </div>
                            <div class="pos-modal-exito-dato">
                                <span class="pos-modal-exito-dato-etiqueta">Aparatos</span>
                                <span class="pos-modal-exito-dato-valor">{{ $venta->detalles->count() }}</span>
                            </div>
                            <div class="pos-modal-exito-dato">
                                <span class="pos-modal-exito-dato-etiqueta">Método de pago</span>
                                <span class="pos-modal-exito-dato-valor">{{ $metodosPago[$venta->metodo_pago] ?? $venta->metodo_pago }}</span>
                            </div>
                            @if ($venta->metodo_pago === 'mixto')
                                <div class="pos-modal-exito-dato">
                                    <span class="pos-modal-exito-dato-etiqueta">Efectivo / QR</span>
                                    <span class="pos-modal-exito-dato-valor">
                                        Bs {{ number_format((float) $venta->monto_efectivo, 2, ',', '.') }}
                                        · Bs {{ number_format((float) $venta->monto_qr, 2, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                            @if ($venta->qrCobro)
                                <div class="pos-modal-exito-dato">
                                    <span class="pos-modal-exito-dato-etiqueta">QR usado</span>
                                    <span class="pos-modal-exito-dato-valor">{{ $venta->qrCobro->nombre }}</span>
                                </div>
                            @endif
                            <div class="pos-modal-exito-dato">
                                <span class="pos-modal-exito-dato-etiqueta">Descuento</span>
                                <span class="pos-modal-exito-dato-valor">Bs {{ number_format((float) $venta->descuento, 2, ',', '.') }}</span>
                            </div>
                            <div class="pos-modal-exito-dato" style="border-bottom: 0;">
                                <span class="pos-modal-exito-dato-etiqueta pos-modal-exito-total-label">Total cobrado</span>
                                <span class="pos-modal-exito-dato-valor pos-modal-exito-total-valor">Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</span>
                            </div>
                        </div>

                        <ul class="pos-modal-exito-items mb-4">
                            @foreach ($venta->detalles as $detalle)
                                <li class="pos-modal-exito-item">
                                    <div class="min-w-0">
                                        <span class="pos-modal-exito-item-nombre d-block">{{ $detalle->producto?->nombre }}</span>
                                        <span class="pos-modal-exito-item-codigo">{{ $detalle->unidad?->codigo_interno }}</span>
                                    </div>
                                    <span class="pos-modal-exito-item-precio">
                                        Bs {{ number_format((float) $detalle->precio_unitario - (float) $detalle->descuento, 2, ',', '.') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($venta->comprobante_url)
                            <a href="{{ $venta->comprobante_url }}" target="_blank" rel="noopener"
                                class="pos-modal-exito-respaldo w-100 mb-2 text-center justify-content-center">
                                <i class="ri-image-line"></i> Ver el respaldo del pago
                            </a>
                        @endif

                        {{-- El recibo se descarga con un enlace normal, no con una
                             acción de Livewire: una descarga es una respuesta con su
                             propio Content-Type y Livewire responde JSON. Va a otra
                             pestaña para que el modal siga abierto y se pueda seguir
                             con la venta siguiente. --}}
                        <a href="{{ route('ventas.recibo', $venta) }}" target="_blank" rel="noopener"
                            class="pos-modal-exito-respaldo w-100 mb-2 text-center justify-content-center">
                            <i class="ri-file-download-line"></i> Descargar recibo (PDF)
                        </a>

                        <button type="button" class="btn w-100 pos-modal-exito-nueva" wire:click="nuevaVenta">
                            <i class="ri-add-line align-bottom me-1"></i> Nueva venta
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Quitar un aparato del carrito ===================== --}}
    {{-- Un toque de más en el mostrador borraba la línea sin aviso, y con el
         carrito medio armado no siempre se nota cuál faltaba. --}}
    <div class="modal fade zoomIn" id="modalQuitarLinea" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-3">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-close-circle-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Quitar este aparato?</h5>

                    @if ($this->lineaAQuitar)
                        <div class="pos-modal-aparato mb-3">
                            <div class="fw-semibold">{{ $this->lineaAQuitar->producto?->nombre ?? 'Producto' }}</div>
                            <div class="text-muted fs-12">
                                <code>{{ $this->lineaAQuitar->codigo_interno }}</code>
                                @if ($this->lineaAQuitar->serial) · S/N {{ $this->lineaAQuitar->serial }} @endif
                            </div>
                        </div>
                    @endif

                    <p class="text-muted fs-13 mb-4">
                        Sale del carrito, no del stock: sigue disponible para vender.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="quitar">
                            Sí, quitar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Vaciar el carrito ===================== --}}
    <div class="modal fade zoomIn" id="modalVaciarCarrito" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-3">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-delete-bin-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Vaciar el carrito?</h5>
                    <p class="text-muted fs-13 mb-4">
                        Se quitan los {{ count($carrito) }}
                        {{ count($carrito) === 1 ? 'aparato' : 'aparatos' }} y también el cliente,
                        el método de pago y las notas de esta venta. Ningún aparato sale del stock.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="vaciarCarrito">
                            Sí, vaciar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Confirmar el cobro ===================== --}}
    {{-- Última parada antes de guardar: la venta solo se deshace anulándola,
         y la anulación deja su rastro en el histórico y en el kardex. --}}
    <div class="modal fade" id="modalConfirmarCobro" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content">
                <div class="modal-header modal-crud-header p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="ri-shield-check-line"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">Revisa la venta antes de cobrar</h5>
                            <small class="text-muted">
                                Una vez registrada solo se puede anular, y la anulación queda en el histórico.
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body modal-crud-body p-4">
                    <div class="pos-confirmar-lista">
                        @foreach ($carrito as $indice => $linea)
                            @php
                                $u = $this->unidadesDelCarrito[$linea['unidad_id']] ?? null;
                                $descuentoLinea = $this->descuentoDeLinea($linea) / 100;
                            @endphp
                            <div class="pos-confirmar-item" wire:key="confirmar-{{ $linea['unidad_id'] }}">
                                <span class="pos-carrito-item-num">{{ $indice + 1 }}</span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold text-truncate">
                                        {{ $u?->producto?->nombre ?? 'Producto' }}
                                    </div>
                                    <div class="text-muted fs-12 text-truncate">
                                        <code>{{ $u?->codigo_interno }}</code>
                                        @if ($u?->serial) · S/N {{ $u->serial }} @endif
                                        @if ($u?->producto?->marca) · {{ $u->producto->marca->nombre }} @endif
                                    </div>
                                    @if ($descuentoLinea > 0)
                                        <div class="fs-11" style="color: #c62828;">
                                            Referencia Bs {{ number_format((float) $linea['precio_lista'], 2, ',', '.') }}
                                            · rebaja Bs {{ number_format($descuentoLinea, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-end fw-semibold flex-shrink-0">
                                    Bs {{ number_format((float) $linea['precio'], 2, ',', '.') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pos-confirmar-resumen">
                        <div class="pos-confirmar-fila">
                            <span>Cliente</span>
                            <strong>{{ $this->clienteElegido?->persona?->nombre_completo ?? 'Público general' }}</strong>
                        </div>
                        <div class="pos-confirmar-fila">
                            <span>Método de pago</span>
                            <strong>{{ $metodosPago[$metodoPago] ?? $metodoPago }}</strong>
                        </div>
                        @if ($metodoPago === 'mixto')
                            <div class="pos-confirmar-fila">
                                <span>Efectivo / QR</span>
                                <strong>
                                    Bs {{ number_format((float) ($montoEfectivo ?: 0), 2, ',', '.') }}
                                    · Bs {{ number_format((float) ($montoQr ?: 0), 2, ',', '.') }}
                                </strong>
                            </div>
                        @endif
                        @if ($this->pagoUsaQr && $this->qrElegido)
                            <div class="pos-confirmar-fila">
                                <span>QR y respaldo</span>
                                <strong>
                                    {{ $this->qrElegido->nombre }}
                                    @if ($comprobante) · comprobante adjunto @endif
                                </strong>
                            </div>
                        @endif
                        @if (trim($notas) !== '')
                            <div class="pos-confirmar-fila">
                                <span>Notas</span>
                                <strong class="text-end">{{ $notas }}</strong>
                            </div>
                        @endif
                        <div class="pos-confirmar-fila pos-confirmar-total">
                            <span>Total a cobrar</span>
                            <strong>Bs {{ number_format($this->totalEnCentavos / 100, 2, ',', '.') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="modal-footer modal-crud-footer p-4">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light modal-cancelar flex-grow-1" data-bs-dismiss="modal">
                            <i class="ri-arrow-go-back-line align-bottom me-1"></i> Revisar de nuevo
                        </button>
                        <button type="button" class="btn pos-cobrar flex-grow-1"
                            wire:click="cobrar" wire:loading.attr="disabled" wire:target="cobrar">
                            <span wire:loading.remove wire:target="cobrar">
                                <i class="ri-check-double-line align-bottom me-1"></i> Sí, cobrar y registrar
                            </span>
                            <span wire:loading wire:target="cobrar">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Registrando venta...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
