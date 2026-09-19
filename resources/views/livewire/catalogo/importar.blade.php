<div class="importar-catalogo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-upload-cloud-2-line me-1"></i>
                            Catálogo · Carga masiva
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-file-excel-2-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Importar catálogo</h4>
                                <p class="text-white-50 mb-0">
                                    Sube categorías, subcategorías y productos de una vez desde un Excel.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            <button type="button" class="btn btn-light crud-nueva-hero"
                                wire:click="descargarPlantilla" wire:loading.attr="disabled"
                                wire:target="descargarPlantilla">
                                <span wire:loading.remove wire:target="descargarPlantilla">
                                    <i class="ri-download-2-line align-bottom me-1"></i> Descargar plantilla
                                </span>
                                <span wire:loading wire:target="descargarPlantilla">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Generando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Cómo se usa ===================== --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">
                        <i class="ri-lightbulb-line align-middle me-1 text-warning"></i>
                        Cómo se usa
                    </h5>
                </div>
                <div class="card-body">
                    <ol class="ps-3 mb-0 text-muted fs-14">
                        <li class="mb-2">Pulsa <strong>Descargar plantilla</strong> para obtener el Excel con el formato exacto.</li>
                        <li class="mb-2">Rellena la hoja <strong>Categorias</strong> y, debajo, la hoja <strong>Productos</strong>.</li>
                        <li class="mb-2">En <em>Categoria padre</em> deja vacío para una categoría principal o escribe el nombre de otra para hacerla subcategoría.</li>
                        <li class="mb-2">Los productos apuntan a una categoría por su nombre; si usas subcategorías, escribe el padre en <em>Categoria</em> y la hija en <em>Subcategoria</em>.</li>
                        <li class="mb-2">Las filas de ejemplo empiezan con <code>#</code>: se ignoran. Bórralas o reemplázalas.</li>
                        <li>Si algo ya existe (mismo nombre), se <strong>actualiza</strong> en lugar de duplicarse.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- ===================== Subir archivo ===================== --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">
                        <i class="ri-file-upload-line align-middle me-1"></i>
                        Subir archivo
                    </h5>
                </div>
                <div class="card-body">
                    <label for="archivo-catalogo"
                        class="border rounded-3 d-flex flex-column align-items-center justify-content-center text-center p-4 mb-3"
                        style="border-style: dashed !important; cursor: pointer;">
                        <i class="ri-file-excel-2-line fs-1 text-success"></i>
                        <span class="fw-semibold mt-2">
                            @if ($archivo)
                                {{ $archivo->getClientOriginalName() }}
                            @else
                                Toca para elegir el archivo Excel
                            @endif
                        </span>
                        <span class="text-muted fs-13">.xlsx o .csv · máximo 5 MB</span>
                        <input id="archivo-catalogo" type="file" class="d-none"
                            accept=".xlsx,.csv" wire:model="archivo">
                    </label>

                    <div wire:loading wire:target="archivo" class="text-muted fs-13 mb-2">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Cargando archivo...
                    </div>

                    @error('archivo')
                        <div class="alert alert-danger py-2 fs-13 mb-2">{{ $message }}</div>
                    @enderror

                    @if ($error)
                        <div class="alert alert-danger py-2 fs-13 mb-2">
                            <i class="ri-error-warning-line me-1"></i>{{ $error }}
                        </div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-grow-1"
                            wire:click="importar" wire:loading.attr="disabled"
                            wire:target="importar" @disabled($archivo === null)>
                            <span wire:loading.remove wire:target="importar">
                                <i class="ri-upload-cloud-2-line align-bottom me-1"></i> Importar
                            </span>
                            <span wire:loading wire:target="importar">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Importando...
                            </span>
                        </button>
                        @if ($archivo !== null || $resultado !== null || $error !== null)
                            <button type="button" class="btn btn-light" wire:click="limpiar">
                                Limpiar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Resultado ===================== --}}
    @if ($resultado !== null)
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">
                    <i class="ri-checkbox-circle-line align-middle me-1 text-success"></i>
                    Resultado de la importación
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-lg-3">
                        <x-stat-card label="Categorías creadas" value="{{ $resultado['categorias_creadas'] }}"
                            icon="bx-folder-plus" color="success" caption="Nuevas en el árbol" />
                    </div>
                    <div class="col-6 col-lg-3">
                        <x-stat-card label="Categorías actualizadas" value="{{ $resultado['categorias_actualizadas'] }}"
                            icon="bx-refresh" color="info" caption="Ya existían" />
                    </div>
                    <div class="col-6 col-lg-3">
                        <x-stat-card label="Productos creados" value="{{ $resultado['productos_creados'] }}"
                            icon="bx-package" color="primary" caption="Nuevos en el catálogo" />
                    </div>
                    <div class="col-6 col-lg-3">
                        <x-stat-card label="Productos actualizados" value="{{ $resultado['productos_actualizados'] }}"
                            icon="bx-edit" color="warning" caption="Ya existían" />
                    </div>
                </div>

                @if ($resultado['marcas_creadas'] > 0)
                    <p class="text-muted mb-3">
                        <i class="ri-store-2-line me-1"></i>
                        Se crearon <strong>{{ $resultado['marcas_creadas'] }}</strong> marca(s) que no existían.
                    </p>
                @endif

                @if ($resultado['errores'] !== [])
                    <div class="alert alert-warning mb-0">
                        <h6 class="alert-heading">
                            <i class="ri-error-warning-line me-1"></i>
                            {{ count($resultado['errores']) }} fila(s) con problemas
                        </h6>
                        <ul class="mb-0 ps-3 fs-13">
                            @foreach ($resultado['errores'] as $linea)
                                <li>{{ $linea }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
