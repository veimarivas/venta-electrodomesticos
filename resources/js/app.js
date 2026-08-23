import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import Swal from 'sweetalert2';

// WebSockets del dashboard en vivo. Se importa antes que nada para que
// window.Echo exista cuando Livewire enlace sus listeners `echo-private:`.
import './echo';

// Gráficas dinámicas de la página de reportes (Chart.js).
import './reportes-charts';

/*
|------------------------------------------------------------------------------
| Bundle propio del proyecto
|------------------------------------------------------------------------------
| Se carga después de assets/js/app.js (el de Velzon). Aquí va únicamente el
| comportamiento del sistema, no el de la plantilla.
*/

flatpickr.localize(Spanish);

/**
 * Toast de esquina reutilizable. Se usa desde Blade para los mensajes flash
 * y desde cualquier script para avisos puntuales.
 *
 * @param {'success'|'error'|'warning'|'info'} icon
 * @param {string} title
 */
window.toast = (icon, title) => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
    });
};

/**
 * Confirmación antes de una acción destructiva. Se activa marcando el
 * formulario con data-confirm="¿Texto de la pregunta?".
 *
 *   <form method="POST" data-confirm="¿Anular esta venta?"> ... </form>
 */
const bindConfirmForms = (root = document) => {
    root.querySelectorAll('form[data-confirm]:not([data-confirm-bound])').forEach((form) => {
        form.setAttribute('data-confirm-bound', '');

        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();

            Swal.fire({
                title: form.dataset.confirm,
                text: form.dataset.confirmText || 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Sí, continuar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger w-xs me-2',
                    cancelButton: 'btn btn-light w-xs',
                },
                buttonsStyling: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });
};

/**
 * Selectores de fecha.
 *
 * Se activan con data-datepicker, NO con data-provider="flatpickr". Ese es el
 * convenio de la plantilla, y assets/js/app.js recorre todos los elementos con
 * data-provider leyendo data-date-format sin comprobar que exista, lo que lanza
 * un TypeError y corta el resto de su inicialización. Con un atributo propio
 * la plantilla los ignora y no hay conflicto.
 *
 *   <input data-datepicker data-range data-date-format="d/m/Y">
 */
const bindDatePickers = (root = document) => {
    root.querySelectorAll('[data-datepicker]:not(.flatpickr-input)').forEach((input) => {
        flatpickr(input, {
            mode: input.hasAttribute('data-range') ? 'range' : 'single',
            dateFormat: input.dataset.dateFormat || 'd/m/Y',
            defaultDate: input.dataset.defaultDate || null,
            maxDate: input.dataset.maxDate || null,
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    bindConfirmForms();
    bindDatePickers();
});

/*
|------------------------------------------------------------------------------
| Árbol de categorías: reordenar arrastrando
|------------------------------------------------------------------------------
| La tabla de categorías es un árbol aplanado en filas: cada <tr> lleva su id,
| el id de su padre y su profundidad. Arrastrando el asa de una fila y soltándola
| se reubica la categoría, y el destino se interpreta según en qué franja de la
| fila de destino se suelte:
|
|   · borde superior  -> hermana justo antes de esa fila
|   · centro          -> subcategoría (hija) de esa fila
|   · borde inferior  -> hermana justo después de esa fila
|
| De ahí salen los dos datos que define la jerarquía —padre e índice entre
| hermanos— y se envían al componente Livewire, que persiste las posiciones.
|
| Todo se engancha por delegación en document: Livewire reemplaza el <tbody>
| en cada actualización y unos listeners atados a las filas se perderían.
*/
const CATEGORIAS = {
    tabla: '[data-categorias-ordenables]',
    // Franja de cada extremo de la fila que significa "hermana", en tanto por
    // uno de su altura. El 60% central queda para "hija".
    margen: 0.2,
};

/** Fila que se está arrastrando ahora mismo, o null. */
let categoriaArrastrada = null;

const filasCategorias = (tabla) => Array.from(tabla.querySelectorAll('tbody tr[data-categoria-id]'));

/** Clave de agrupación por padre; la raíz es cadena vacía. */
const padreDe = (fila) => fila.dataset.padreId || '';

/**
 * ¿La fila de destino cuelga de la que se arrastra? Mover un padre dentro de
 * su propia descendencia rompería el árbol. El servidor lo vuelve a comprobar,
 * pero conviene no ofrecer siquiera el destino.
 */
const esDescendiente = (destino, origen, tabla) => {
    const padres = new Map(filasCategorias(tabla).map((fila) => [fila.dataset.categoriaId, padreDe(fila)]));

    let actual = destino.dataset.categoriaId;

    while (actual) {
        if (actual === origen.dataset.categoriaId) {
            return true;
        }

        actual = padres.get(actual) || '';
    }

    return false;
};

/** Franja de la fila donde está el cursor: 'antes' | 'dentro' | 'despues'. */
const zonaDeSoltado = (fila, evento) => {
    const caja = fila.getBoundingClientRect();
    const proporcion = (evento.clientY - caja.top) / caja.height;

    if (proporcion < CATEGORIAS.margen) {
        return 'antes';
    }

    return proporcion > 1 - CATEGORIAS.margen ? 'despues' : 'dentro';
};

const limpiarPistas = (tabla) => {
    tabla.querySelectorAll('.categoria-destino-antes, .categoria-destino-dentro, .categoria-destino-despues')
        .forEach((fila) => {
            fila.classList.remove('categoria-destino-antes', 'categoria-destino-dentro', 'categoria-destino-despues');
        });
};

/**
 * Traduce fila de destino + zona al par (padre, índice) que espera el backend.
 * El índice se calcula sobre la lista de hermanos SIN la fila arrastrada, que
 * es exactamente como la reconstruye el componente al recolocar.
 */
const destinoDelSoltado = (origen, destino, zona, tabla) => {
    const filas = filasCategorias(tabla).filter((fila) => fila !== origen);

    if (zona === 'dentro') {
        const hijos = filas.filter((fila) => padreDe(fila) === destino.dataset.categoriaId);

        // Al anidar se cuelga al final de las hijas que ya tenga.
        return { padreId: destino.dataset.categoriaId, indice: hijos.length };
    }

    const hermanos = filas.filter((fila) => padreDe(fila) === padreDe(destino));
    const posicion = hermanos.indexOf(destino);

    return {
        padreId: padreDe(destino) || null,
        indice: zona === 'antes' ? posicion : posicion + 1,
    };
};

document.addEventListener('dragstart', (evento) => {
    const asa = evento.target.closest('[data-categoria-asa]');

    if (!asa) {
        return;
    }

    categoriaArrastrada = asa.closest('tr[data-categoria-id]');

    if (!categoriaArrastrada) {
        return;
    }

    categoriaArrastrada.classList.add('categoria-arrastrando');

    evento.dataTransfer.effectAllowed = 'move';
    // Firefox no inicia el arrastre si no se escribe algo en dataTransfer.
    evento.dataTransfer.setData('text/plain', categoriaArrastrada.dataset.categoriaId);

    if (evento.dataTransfer.setDragImage) {
        evento.dataTransfer.setDragImage(categoriaArrastrada, 24, 16);
    }
});

document.addEventListener('dragover', (evento) => {
    if (!categoriaArrastrada) {
        return;
    }

    const tabla = categoriaArrastrada.closest(CATEGORIAS.tabla);
    const destino = evento.target.closest?.('tr[data-categoria-id]');

    if (!tabla) {
        return;
    }

    limpiarPistas(tabla);

    if (!destino || !tabla.contains(destino) || destino === categoriaArrastrada) {
        return;
    }

    if (esDescendiente(destino, categoriaArrastrada, tabla)) {
        evento.dataTransfer.dropEffect = 'none';

        return;
    }

    // Sin preventDefault el navegador no considera la fila un destino válido.
    evento.preventDefault();
    evento.dataTransfer.dropEffect = 'move';

    destino.classList.add(`categoria-destino-${zonaDeSoltado(destino, evento)}`);
});

document.addEventListener('drop', (evento) => {
    if (!categoriaArrastrada) {
        return;
    }

    const tabla = categoriaArrastrada.closest(CATEGORIAS.tabla);
    const destino = evento.target.closest?.('tr[data-categoria-id]');

    if (!tabla) {
        return;
    }

    limpiarPistas(tabla);

    if (!destino || !tabla.contains(destino) || destino === categoriaArrastrada) {
        return;
    }

    if (esDescendiente(destino, categoriaArrastrada, tabla)) {
        return;
    }

    evento.preventDefault();

    const zona = zonaDeSoltado(destino, evento);
    const { padreId, indice } = destinoDelSoltado(categoriaArrastrada, destino, zona, tabla);
    const componente = tabla.closest('[wire\\:id]');

    if (componente) {
        Livewire.find(componente.getAttribute('wire:id')).call(
            'moverCategoria',
            Number(categoriaArrastrada.dataset.categoriaId),
            padreId === null ? null : Number(padreId),
            indice,
        );
    }
});

document.addEventListener('dragend', () => {
    if (!categoriaArrastrada) {
        return;
    }

    const tabla = categoriaArrastrada.closest(CATEGORIAS.tabla);

    categoriaArrastrada.classList.remove('categoria-arrastrando');

    if (tabla) {
        limpiarPistas(tabla);
    }

    categoriaArrastrada = null;
});

/*
|------------------------------------------------------------------------------
| Puente entre Livewire y la plantilla
|------------------------------------------------------------------------------
| Los componentes despachan eventos desde PHP y aquí se traducen a acciones de
| Bootstrap (abrir/cerrar modales) o a un toast. Se engancha en 'livewire:init'
| para no depender del orden en que se carguen los scripts.
*/
const modal = (id) => {
    const el = document.getElementById(id);

    return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
};

document.addEventListener('livewire:init', () => {
    Livewire.on('toast', (payload) => {
        // Livewire entrega los parámetros con nombre dentro de un array.
        const { tipo = 'success', mensaje = '' } = Array.isArray(payload) ? payload[0] : payload;
        window.toast(tipo, mensaje);
    });

    // Cada módulo declara qué evento abre y cierra cada modal.
    const modales = {
        'modal-persona': 'modalPersona',
        'modal-eliminar': 'modalEliminarPersona',
        'modal-categoria': 'modalCategoria',
        'modal-eliminar-categoria': 'modalEliminarCategoria',
        'modal-marca': 'modalMarca',
        'modal-eliminar-marca': 'modalEliminarMarca',
        'modal-producto': 'modalProducto',
        'modal-eliminar-producto': 'modalEliminarProducto',
        'modal-item': 'modalItem',
        'modal-eliminar-item': 'modalEliminarItem',
        'modal-proveedor': 'modalProveedor',
        'modal-eliminar-proveedor': 'modalEliminarProveedor',
        'modal-compra': 'modalCompra',
        'modal-seriales-compra': 'modalSerialesCompra',
        'modal-eliminar-compra': 'modalEliminarCompra',
        'modal-cargo': 'modalCargo',
        'modal-eliminar-cargo': 'modalEliminarCargo',
        'modal-venta-registrada': 'modalVentaRegistrada',
        'modal-quitar-linea': 'modalQuitarLinea',
        'modal-vaciar-carrito': 'modalVaciarCarrito',
        'modal-confirmar-cobro': 'modalConfirmarCobro',
        'modal-cliente-pos': 'modalClientePos',
        'modal-qr': 'modalQr',
        'modal-eliminar-qr': 'modalEliminarQr',
        'modal-anular-venta': 'modalAnularVenta',
        'modal-recibo': 'modalRecibo',
        'modal-cliente': 'modalCliente',
        'modal-archivar-cliente': 'modalArchivarCliente',
        'modal-trabajador': 'modalTrabajador',
        'modal-editar-trabajador': 'modalEditarTrabajador',
        'modal-baja-trabajador': 'modalBajaTrabajador',
        'modal-cuenta-trabajador': 'modalCuentaTrabajador',
        'modal-reiniciar-password': 'modalReiniciarPassword',
        'modal-usuario': 'modalUsuario',
        'modal-eliminar-usuario': 'modalEliminarUsuario',
        'modal-rol': 'modalRol',
        'modal-permisos-rol': 'modalPermisosRol',
        'modal-eliminar-rol': 'modalEliminarRol',
    };

    Object.entries(modales).forEach(([evento, id]) => {
        Livewire.on(`abrir-${evento}`, () => modal(id)?.show());
        Livewire.on(`cerrar-${evento}`, () => modal(id)?.hide());
    });
});

// Los date pickers y confirmaciones deben re-enlazarse en el HTML que Livewire
// inserta tras cada actualización, si no dejan de funcionar al paginar.
document.addEventListener('livewire:navigated', () => {
    bindConfirmForms();
    bindDatePickers();
});

/*
|------------------------------------------------------------------------------
| Tiempo real (fase de dashboard en vivo)
|------------------------------------------------------------------------------
| Cuando se instale Laravel Reverb, descomentar este bloque y agregar los
| paquetes laravel-echo y pusher-js. El dashboard escuchará el canal privado
| "sales" para actualizar los indicadores sin recargar la página.
|
| import Echo from 'laravel-echo';
| import Pusher from 'pusher-js';
|
| window.Pusher = Pusher;
| window.Echo = new Echo({
|     broadcaster: 'reverb',
|     key: import.meta.env.VITE_REVERB_APP_KEY,
|     wsHost: import.meta.env.VITE_REVERB_HOST,
|     wsPort: import.meta.env.VITE_REVERB_PORT,
|     forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
|     enabledTransports: ['ws', 'wss'],
| });
*/

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/*
|------------------------------------------------------------------------------
| Capa de interacción de las gráficas
|------------------------------------------------------------------------------
| Una gráfica en HTML es interactiva por defecto: el tooltip no es un extra, es
| parte de la entrega. Dos comportamientos:
|
|   · Serie de tiempo -> una cruz vertical sigue al puntero y se ENGANCHA al
|     dato más cercano. El lector apunta a una fecha, nunca a una línea de 2px.
|   · Barras y segmentos -> cada marca es su propio blanco, con su tooltip.
|
| El tooltip solo REFUERZA: todo valor que muestra está también en una etiqueta
| directa o en la tabla, así que nadie queda fuera por no poder pasar el ratón.
|
| Todo por delegación en document: Livewire reemplaza el DOM en cada
| actualización y unos listeners atados a los nodos se perderían.
*/
let vizTooltip = null;

const vizObtenerTooltip = () => {
    if (!vizTooltip) {
        vizTooltip = document.createElement('div');
        vizTooltip.className = 'viz-tooltip';
        vizTooltip.setAttribute('role', 'tooltip');
        document.body.appendChild(vizTooltip);
    }

    return vizTooltip;
};

/**
 * Pinta el tooltip. Los textos llegan como datos del servidor, así que se
 * insertan con textContent y nunca con innerHTML.
 *
 * @param {{titulo: string, filas: Array<{serie: string, valor: string, color: string}>}} contenido
 */
const vizMostrarTooltip = (contenido, x, y) => {
    const tip = vizObtenerTooltip();
    tip.textContent = '';

    const titulo = document.createElement('div');
    titulo.className = 'viz-tooltip-titulo';
    titulo.textContent = contenido.titulo;
    tip.appendChild(titulo);

    contenido.filas.forEach((fila) => {
        const linea = document.createElement('div');
        linea.className = 'viz-tooltip-fila';

        if (fila.color) {
            const clave = document.createElement('span');
            clave.className = 'viz-tooltip-clave';
            clave.style.background = fila.color;
            linea.appendChild(clave);
        }

        // El valor manda; el nombre de la serie va detrás y en tono suave.
        const valor = document.createElement('span');
        valor.className = 'viz-tooltip-valor';
        valor.textContent = fila.valor;
        linea.appendChild(valor);

        if (fila.serie) {
            const serie = document.createElement('span');
            serie.className = 'viz-tooltip-serie';
            serie.textContent = fila.serie;
            linea.appendChild(serie);
        }

        tip.appendChild(linea);
    });

    tip.classList.add('esta-visible');

    // Se recoloca dentro de la ventana para que no se salga por los bordes.
    const caja = tip.getBoundingClientRect();
    const margen = 12;
    const izquierda = Math.min(Math.max(x + 14, margen), window.innerWidth - caja.width - margen);
    const arriba = Math.min(Math.max(y - caja.height - 12, margen), window.innerHeight - caja.height - margen);

    tip.style.left = `${izquierda}px`;
    tip.style.top = `${arriba}px`;
};

const vizOcultarTooltip = () => {
    vizTooltip?.classList.remove('esta-visible');
};

/** Marcas simples: barras, segmentos apilados. El dato viaja en el elemento. */
const vizTooltipDeMarca = (elemento, evento) => {
    vizMostrarTooltip({
        titulo: elemento.dataset.vizTitulo || '',
        filas: [{
            serie: elemento.dataset.vizSerie || '',
            valor: elemento.dataset.vizValor || '',
            color: elemento.dataset.vizColor || '',
        }],
    }, evento.clientX, evento.clientY);
};

document.addEventListener('pointermove', (evento) => {
    const marca = evento.target.closest?.('[data-viz-valor]');

    if (marca) {
        vizTooltipDeMarca(marca, evento);

        return;
    }

    // Serie de tiempo: se busca el punto más cercano en X.
    const grafica = evento.target.closest?.('[data-viz-serie-tiempo]');

    if (!grafica) {
        vizOcultarTooltip();

        return;
    }

    let puntos;

    try {
        puntos = JSON.parse(grafica.dataset.vizSerieTiempo);
    } catch {
        return;
    }

    if (!puntos.length) {
        return;
    }

    const caja = grafica.getBoundingClientRect();
    const proporcion = (evento.clientX - caja.left) / caja.width;
    const indice = Math.min(puntos.length - 1, Math.max(0, Math.round(proporcion * (puntos.length - 1))));
    const punto = puntos[indice];

    // La cruz se engancha al dato, no al píxel del puntero.
    const cruz = grafica.querySelector('.viz-cruz');
    const marcador = grafica.querySelector('.viz-punto-activo');
    const x = puntos.length > 1 ? (indice / (puntos.length - 1)) * 100 : 50;

    if (cruz) {
        cruz.setAttribute('x1', `${x}%`);
        cruz.setAttribute('x2', `${x}%`);
        cruz.style.display = '';
    }

    if (marcador) {
        marcador.setAttribute('cx', `${x}%`);
        marcador.setAttribute('cy', punto.y);
        marcador.style.display = '';
    }

    vizMostrarTooltip({
        titulo: punto.titulo,
        filas: punto.filas,
    }, evento.clientX, evento.clientY);
});

document.addEventListener('pointerleave', vizOcultarTooltip, true);

// Al desplazar la página el tooltip quedaría flotando lejos de su marca.
window.addEventListener('scroll', vizOcultarTooltip, { passive: true });

// Mismo detalle con el teclado que con el ratón: las marcas enfocables
// muestran su tooltip al recibir el foco.
document.addEventListener('focusin', (evento) => {
    const marca = evento.target.closest?.('[data-viz-valor]');

    if (!marca) {
        return;
    }

    const caja = marca.getBoundingClientRect();

    vizMostrarTooltip({
        titulo: marca.dataset.vizTitulo || '',
        filas: [{
            serie: marca.dataset.vizSerie || '',
            valor: marca.dataset.vizValor || '',
            color: marca.dataset.vizColor || '',
        }],
    }, caja.left + caja.width / 2, caja.top);
});

document.addEventListener('focusout', vizOcultarTooltip);
