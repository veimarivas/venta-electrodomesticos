/*
|------------------------------------------------------------------------------
| Avisos en vivo con sonido
|------------------------------------------------------------------------------
| El administrador tiene que enterarse de que un vendedor pidió autorizar un
| descuento SIN estar mirando la pantalla, porque el vendedor está con el
| cliente delante esperando respuesta.
|
| El aviso ya se guarda en la base (lo pinta la campana) y ya viaja por el
| canal privado `autorizaciones` de Reverb. Aquí solo se escucha ese canal en
| cualquier pantalla del panel y se hace lo que la campana no puede sola:
|
|   · Suena. Es lo único que cruza la habitación.
|   · Sube el contador y mete el aviso en la lista al instante, sin recargar.
|
| El sonido se genera con la Web Audio API y no con un archivo: no hay que
| versionar un mp3 ni servir un asset que puede no cargar, y suena igual en
| cualquier equipo. Los navegadores no dejan sonar hasta que el usuario
| interactúa, así que el contexto se «despierta» con el primer clic o tecla
| —que ocurre al entrar y moverse por el panel—.
*/

/** Contorno de la campanilla de dos notas. */
const AVISOS_FRECUENCIAS = [880, 1174.66];

/** Forma que tiene en la campana cada tipo de aviso. */
const AVISOS_APARIENCIA = {
    solicitud_descuento: {
        icono: 'bx-purchase-tag-alt',
        clase: 'bg-warning-subtle text-warning',
    },
    stock_bajo: {
        icono: 'bx-package',
        clase: 'bg-danger-subtle text-danger',
    },
    cuota_por_cobrar: {
        icono: 'bx-wallet',
        clase: 'bg-info-subtle text-info',
    },
    venta_registrada: {
        icono: 'bx-cart',
        clase: 'bg-success-subtle text-success',
    },
};

let contextoAudio = null;

/**
 * Crea (o reanuda) el contexto de audio. Devuelve null si el navegador no lo
 * soporta, para que nadie tenga que comprobarlo por su cuenta.
 */
const despertarAudio = () => {
    const Constructor = window.AudioContext || window.webkitAudioContext;

    if (!Constructor) {
        return null;
    }

    if (!contextoAudio) {
        try {
            contextoAudio = new Constructor();
        } catch (_) {
            return null;
        }
    }

    if (contextoAudio.state === 'suspended') {
        contextoAudio.resume();
    }

    return contextoAudio;
};

// El primer gesto del usuario es lo que habilita el sonido en el navegador.
document.addEventListener('pointerdown', despertarAudio);
document.addEventListener('keydown', despertarAudio);

/** Dos notas cortas, con rampa de entrada y salida para que no chasquee. */
const sonarAviso = () => {
    const contexto = despertarAudio();

    if (!contexto) {
        return;
    }

    const ahora = contexto.currentTime;

    AVISOS_FRECUENCIAS.forEach((frecuencia, indice) => {
        const oscilador = contexto.createOscillator();
        const ganancia = contexto.createGain();

        oscilador.type = 'sine';
        oscilador.frequency.value = frecuencia;
        oscilador.connect(ganancia);
        ganancia.connect(contexto.destination);

        const inicio = ahora + indice * 0.18;

        ganancia.gain.setValueAtTime(0.0001, inicio);
        ganancia.gain.exponentialRampToValueAtTime(0.22, inicio + 0.02);
        ganancia.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.35);

        oscilador.start(inicio);
        oscilador.stop(inicio + 0.4);
    });
};

/** Sube el contador rojo de la campana y ajusta el «N nuevas». */
const subirContador = () => {
    const contador = document.getElementById('notification-count');

    if (contador) {
        const actual = parseInt(contador.textContent, 10) || 0;

        contador.textContent = `${actual + 1}`;
        contador.classList.remove('d-none');
    }

    const nuevas = document.getElementById('notification-new-badge');

    if (nuevas) {
        const actual = parseInt(nuevas.textContent, 10) || 0;

        nuevas.textContent = `${actual + 1} nuevas`;
    }
};

/**
 * Mete el aviso al principio de la lista de la campana, con la misma forma que
 * el HTML que sirve Blade. Todo el texto va por textContent: viene del nombre
 * de un producto y de un vendedor, que son texto libre.
 */
const prependerAviso = ({ icono, clase, url, titulo, cuerpo }) => {
    const lista = document.getElementById('notification-list');

    if (!lista) {
        return;
    }

    document.getElementById('notification-empty')?.remove();

    const fila = document.createElement('div');
    fila.className = 'text-reset notification-item d-block dropdown-item position-relative';

    const envoltorio = document.createElement('div');
    envoltorio.className = 'd-flex';

    const avatar = document.createElement('div');
    avatar.className = 'avatar-xs me-3 flex-shrink-0';

    const marcoIcono = document.createElement('span');
    marcoIcono.className = `avatar-title ${clase} rounded-circle fs-16`;

    const etiquetaIcono = document.createElement('i');
    etiquetaIcono.className = `bx ${icono}`;
    marcoIcono.appendChild(etiquetaIcono);
    avatar.appendChild(marcoIcono);

    const contenido = document.createElement('div');
    contenido.className = 'flex-grow-1';

    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.className = 'stretched-link';

    const encabezado = document.createElement('h6');
    encabezado.className = 'mt-0 mb-2 lh-base';
    encabezado.textContent = titulo;
    enlace.appendChild(encabezado);

    const descripcion = document.createElement('p');
    descripcion.className = 'mb-2 fs-12 text-muted';
    descripcion.textContent = cuerpo;

    const hora = document.createElement('p');
    hora.className = 'mb-0 fs-11 fw-medium text-uppercase text-muted';

    const reloj = document.createElement('span');
    reloj.innerHTML = '<i class="mdi mdi-clock-outline"></i> ';
    reloj.append('ahora');
    hora.appendChild(reloj);

    contenido.append(enlace, descripcion, hora);
    envoltorio.append(avatar, contenido);
    fila.appendChild(envoltorio);

    lista.prepend(fila);
};

/** Cuerpo del aviso de autorización, calcado al que guarda el backend. */
const cuerpoDeSolicitud = (payload) => {
    const producto = payload.producto ?? 'Producto';
    const codigo = payload.codigo ? ` · ${payload.codigo}` : '';
    const precio = Number(payload.precio_solicitado ?? 0).toFixed(2);
    const vendedor = payload.vendedor ?? 'un vendedor';

    return `${producto}${codigo} · pide ${precio} Bs · ${vendedor}`;
};

/** Se suscribe al canal de autorizaciones, solo si el usuario puede resolverlas. */
const iniciarAvisosEnVivo = () => {
    if (!window.Echo || document.body.dataset.puedeAutorizar !== '1') {
        return;
    }

    const urlAutorizaciones =
        document.body.dataset.urlAutorizaciones || '/ventas/autorizaciones';

    window.Echo.private('autorizaciones').listen(
        '.SolicitudDeDescuentoCreada',
        (payload) => {
            const apariencia = AVISOS_APARIENCIA.solicitud_descuento;

            sonarAviso();
            subirContador();
            prependerAviso({
                ...apariencia,
                url: urlAutorizaciones,
                titulo: 'Descuento por autorizar',
                cuerpo: cuerpoDeSolicitud(payload),
            });
        },
    );
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarAvisosEnVivo);
} else {
    iniciarAvisosEnVivo();
}
