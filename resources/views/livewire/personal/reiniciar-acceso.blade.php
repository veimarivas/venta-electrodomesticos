<div>
    <div class="modal fade zoomIn" id="modalReiniciarAcceso" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg modal-crud-content">
                <div class="modal-body p-4">
                    @if (! $hecho)
                        <div class="text-center">
                            <div class="avatar-md mx-auto mb-3">
                                <span class="avatar-title rounded-circle fs-2 bg-warning-subtle text-warning">
                                    <i class="ri-key-2-line"></i>
                                </span>
                            </div>
                            <h5 class="mb-2">¿Reiniciar el acceso de {{ $nombre }}?</h5>
                            <p class="text-muted fs-13 mb-3">
                                Para cuando olvidó su usuario o su contraseña. La cuenta vuelve a:
                            </p>
                        </div>

                        <ul class="list-unstyled reiniciar-acceso-datos mb-3">
                            <li><span>Usuario</span> <code>{{ $usuario }}</code></li>
                            <li><span>Contraseña temporal</span> <strong>su carnet</strong></li>
                        </ul>

                        <p class="text-muted fs-12 mb-4">
                            Se cierran sus sesiones en el panel y en los teléfonos, se borra su huella y,
                            al entrar, el sistema le pide una contraseña propia.
                        </p>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-warning w-100" wire:click="confirmar"
                                wire:loading.attr="disabled" wire:target="confirmar">
                                <i class="ri-restart-line align-bottom me-1"></i> Reiniciar acceso
                            </button>
                        </div>
                    @else
                        <div class="text-center">
                            <div class="avatar-md mx-auto mb-3">
                                <span class="avatar-title rounded-circle fs-2 bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line"></i>
                                </span>
                            </div>
                            <h5 class="mb-1">Acceso reiniciado</h5>
                            <p class="text-muted fs-13 mb-3">Dale estos datos a {{ $nombre }}:</p>
                        </div>

                        <ul class="list-unstyled reiniciar-acceso-datos mb-3"
                            x-data="{ copiado: false }">
                            <li>
                                <span>Usuario</span>
                                <code>{{ $usuario }}</code>
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon"
                                    x-on:click="navigator.clipboard?.writeText(@js($usuario)); copiado = true; setTimeout(() => copiado = false, 1500)"
                                    title="Copiar el usuario">
                                    <i x-bind:class="copiado ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                                </button>
                            </li>
                            <li><span>Contraseña temporal</span> <code>{{ $temporal }}</code> <small class="text-muted">(su carnet)</small></li>
                        </ul>

                        <p class="text-muted fs-12 mb-4">Al entrar con ellos, el sistema le pedirá una contraseña nueva.</p>

                        <div class="d-flex gap-2">
                            @if ($wa = $this->enlaceWhatsapp())
                                <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-success w-100">
                                    <i class="ri-whatsapp-line align-bottom me-1"></i> Enviar por WhatsApp
                                </a>
                            @endif
                            <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">Listo</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
