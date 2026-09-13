import {
    Chart,
    BarController,
    PieController,
    LineController,
    BarElement,
    ArcElement,
    PointElement,
    LineElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

Chart.register(
    BarController,
    PieController,
    LineController,
    BarElement,
    ArcElement,
    PointElement,
    LineElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler,
);

// ---------------------------------------------------------------------------
// Tokens y utilidades
// ---------------------------------------------------------------------------
const FUENTE = "'Poppins', sans-serif";

function cssVar(nombre, fallback = '') {
    return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim() || fallback;
}

function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

/** Color apagado del tema (ticks y etiquetas). */
const apagado = () => cssVar('--marca-apagado', '#6b778a');

/** Color de rejilla, tenue y distinto en claro y oscuro. */
function colorRejilla() {
    return isDark() ? 'rgba(255, 255, 255, .06)' : 'rgba(10, 24, 43, .07)';
}

function formatBs(valor) {
    return 'Bs ' + Number(valor).toLocaleString('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** Versión corta para ejes: 149075 → 149k. */
function formatCompacto(valor) {
    const n = Number(valor);
    if (Math.abs(n) >= 1000000) return (n / 1000000).toFixed(1).replace('.0', '') + 'M';
    if (Math.abs(n) >= 1000) return (n / 1000).toFixed(Math.abs(n) >= 10000 ? 0 : 1).replace('.0', '') + 'k';
    return String(Math.round(n));
}

function truncar(str, max = 18) {
    if (!str) return '';
    return str.length > max ? str.slice(0, max - 1) + '…' : str;
}

function destroyIfExists(id) {
    const existing = Chart.getChart(id);
    if (existing) existing.destroy();
}

function lighten(hex, amount = 0.15) {
    const c = hex.replace('#', '');
    if (c.length !== 6) return hex;
    const r = parseInt(c.substring(0, 2), 16);
    const g = parseInt(c.substring(2, 4), 16);
    const b = parseInt(c.substring(4, 6), 16);
    return `rgb(${Math.min(255, r + (255 - r) * amount)},${Math.min(255, g + (255 - g) * amount)},${Math.min(255, b + (255 - b) * amount)})`;
}

/** ¿El sistema pide reducir el movimiento? */
function sinMovimiento() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function getChartColorsArray(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return null;

    const tema = document.documentElement.getAttribute('data-theme') ?? '';
    const attr = tema ? `data-colors-${tema}` : 'data-colors';
    const raw = el.getAttribute(attr) ?? el.getAttribute('data-colors');
    if (!raw) return null;

    try {
        return JSON.parse(raw).map((token) => {
            const t = token.replace(/\s/g, '');
            if (t.indexOf(',') !== -1) {
                const parts = t.split(',');
                const resuelto = cssVar(parts[0]);
                if (parts.length === 2) return `rgba(${resuelto},${parts[1]})`;
                return resuelto || t;
            }
            return cssVar(t) || t;
        });
    } catch {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Opciones base
// ---------------------------------------------------------------------------
function baseOptions() {
    const oscuro = isDark();

    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: sinMovimiento() ? false : { duration: 750, easing: 'easeOutQuart' },
        interaction: { mode: 'nearest', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                enabled: true,
                backgroundColor: oscuro ? 'rgba(10, 24, 43, .95)' : 'rgba(255, 255, 255, .97)',
                titleColor: oscuro ? '#e6ebf1' : '#0a182b',
                bodyColor: oscuro ? '#cbd5e1' : '#495057',
                borderColor: oscuro ? 'rgba(255, 255, 255, .12)' : '#e9edf2',
                borderWidth: 1,
                cornerRadius: 10,
                padding: 12,
                caretSize: 6,
                titleFont: { size: 13, weight: '600', family: FUENTE },
                bodyFont: { size: 12.5, weight: '500', family: FUENTE },
                bodySpacing: 4,
                boxPadding: 5,
                usePointStyle: true,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: apagado(),
                    font: { size: 11, family: FUENTE },
                    maxRotation: 0,
                    autoSkip: true,
                },
                border: { display: false },
            },
            y: {
                grid: { color: colorRejilla(), drawBorder: false },
                ticks: {
                    color: apagado(),
                    font: { size: 11, family: FUENTE },
                    padding: 6,
                },
                border: { display: false },
                beginAtZero: true,
            },
        },
    };
}

/** Paleta de marca para las gráficas de tarta. */
function paletaMarca() {
    return [
        cssVar('--marca-azul', '#254970'),
        cssVar('--estado-ok', '#1baf7a'),
        cssVar('--marca-oro', '#c5a162'),
        cssVar('--estado-info', '#2a78d6'),
        cssVar('--estado-alerta', '#eda100'),
        cssVar('--estado-error', '#e34948'),
    ];
}

/** Leyenda de tarta: nombre + importe, con punto de color. */
function leyendaTarta(valores, colores) {
    return {
        display: true,
        position: 'bottom',
        labels: {
            color: apagado(),
            font: { size: 11.5, family: FUENTE },
            padding: 12,
            usePointStyle: true,
            pointStyle: 'circle',
            pointStyleWidth: 9,
            boxHeight: 8,
            generateLabels() {
                return valores.map((v, i) => ({
                    text: `${v.nombre} — ${formatBs(v.valor)}`,
                    fillStyle: colores[i % colores.length],
                    strokeStyle: 'transparent',
                    pointStyle: 'circle',
                    hidden: false,
                    index: i,
                }));
            },
        },
    };
}

// ---------------------------------------------------------------------------
// 1. Productos más vendidos — barras horizontales (los nombres son largos)
// ---------------------------------------------------------------------------
function initTopProductos(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const color = cssVar('--marca-azul', '#254970');
    const colorOro = cssVar('--marca-oro', '#c5a162');

    const orden = [...datos].sort((a, b) => Number(b.ingreso) - Number(a.ingreso));
    const labels = orden.map((d) => truncar(d.nombre, 26));
    const values = orden.map((d) => Number(d.ingreso));
    const unidades = orden.map((d) => `${d.unidades} ${d.unidades == 1 ? 'unidad' : 'unidades'}`);

    const opts = baseOptions();
    opts.indexAxis = 'y';
    opts.layout = { padding: { right: 78, left: 4 } };
    opts.scales.x.grid = { color: colorRejilla(), drawBorder: false };
    opts.scales.x.ticks = {
        color: apagado(),
        font: { size: 11, family: FUENTE },
        callback: (v) => formatCompacto(v),
    };
    opts.scales.y.grid = { display: false };
    opts.scales.y.ticks = {
        color: apagado(),
        font: { size: 11, family: FUENTE },
        crossAlign: 'far',
    };
    opts.plugins.tooltip.callbacks = {
        title: (items) => orden[items[0].dataIndex]?.nombre || '',
        label: (item) => formatBs(item.raw),
        afterLabel: (item) => unidades[item.dataIndex] || '',
    };

    const etiquetas = {
        id: 'valorTop_' + canvasId,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.font = `600 11px ${FUENTE}`;
            ctx.fillStyle = apagado();
            meta.data.forEach((bar, i) => {
                ctx.fillText(formatCompacto(values[i]), bar.x + 8, bar.y);
            });
            ctx.restore();
        },
    };

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: values.map((_, i) => (i === 0 ? colorOro : color)),
                hoverBackgroundColor: values.map((_, i) => (i === 0 ? lighten(colorOro, 0.12) : lighten(color, 0.15))),
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.72,
                categoryPercentage: 0.78,
            }],
        },
        options: opts,
        plugins: [etiquetas],
    });
}

// ---------------------------------------------------------------------------
// 2. Ventas por vendedor — barras verticales con valor encima
// ---------------------------------------------------------------------------
function initPorVendedor(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const color = cssVar('--estado-ok', '#1baf7a');

    const labels = datos.map((d) => truncar(d.name, 16));
    const values = datos.map((d) => Number(d.ingreso));
    const ventas = datos.map((d) => `${d.ventas} ${d.ventas == 1 ? 'venta' : 'ventas'}`);

    const opts = baseOptions();
    opts.layout = { padding: { top: 22 } };
    opts.scales.y.ticks.callback = (v) => formatCompacto(v);
    opts.plugins.tooltip.callbacks = {
        title: (items) => datos[items[0].dataIndex]?.name || '',
        label: (item) => formatBs(item.raw),
        afterLabel: (item) => ventas[item.dataIndex] || '',
    };

    const etiquetas = {
        id: 'valorVendedor_' + canvasId,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.font = `600 11px ${FUENTE}`;
            ctx.fillStyle = apagado();
            meta.data.forEach((bar, i) => {
                ctx.fillText(formatCompacto(values[i]), bar.x, bar.y - 6);
            });
            ctx.restore();
        },
    };

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: color,
                hoverBackgroundColor: lighten(color, 0.14),
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.55,
                categoryPercentage: 0.7,
            }],
        },
        options: opts,
        plugins: [etiquetas],
    });
}

// ---------------------------------------------------------------------------
// 3. Cómo se cobró — dona con el total al centro
// ---------------------------------------------------------------------------
function initPorMetodoPago(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const colores = getChartColorsArray(canvasId) || paletaMarca();
    const valores = datos.map((d) => ({ nombre: d.nombre, valor: Number(d.ingreso) }));
    const total = valores.reduce((a, b) => a + b.valor, 0);

    const opts = baseOptions();
    delete opts.scales;
    opts.cutout = '64%';
    opts.plugins.legend = leyendaTarta(valores, colores);
    opts.plugins.tooltip.callbacks = {
        title: (items) => valores[items[0].dataIndex]?.nombre || '',
        label: (item) => {
            const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : '0.0';
            return `${formatBs(item.raw)}  ·  ${pct}%`;
        },
    };

    const centro = {
        id: 'centroDona_' + canvasId,
        afterDraw(chart) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return;
            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = apagado();
            ctx.font = `600 10.5px ${FUENTE}`;
            ctx.fillText('TOTAL', x, y - 12);
            ctx.fillStyle = cssVar('--marca-tinta', '#0a182b');
            ctx.font = `700 15px ${FUENTE}`;
            ctx.fillText(formatCompacto(total), x, y + 6);
            ctx.restore();
        },
    };

    return new Chart(canvas, {
        type: 'pie',
        data: {
            labels: valores.map((v) => v.nombre),
            datasets: [{
                data: valores.map((v) => v.valor),
                backgroundColor: valores.map((_, i) => colores[i % colores.length]),
                hoverBackgroundColor: valores.map((_, i) => lighten(colores[i % colores.length], 0.12)),
                borderColor: isDark() ? '#212529' : '#fff',
                borderWidth: 3,
                hoverOffset: 4,
            }],
        },
        options: opts,
        plugins: [centro],
    });
}

// ---------------------------------------------------------------------------
// 4. Rentabilidad por proveedor — barra apilada horizontal [recuperado, pendiente]
// ---------------------------------------------------------------------------
function colorRecuperado(pct) {
    if (pct >= 100) return cssVar('--estado-ok', '#1baf7a');
    if (pct >= 50) return cssVar('--estado-alerta', '#eda100');
    return cssVar('--estado-error', '#e34948');
}

function initPorProveedor(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const oscuro = isDark();
    const labels = datos.map((d) => truncar(d.nombre, 22));
    const recuperado = datos.map((d) => Math.min(Number(d.recuperado), 100));
    const pendiente = datos.map((d) => Math.max(100 - Math.min(Number(d.recuperado), 100), 0));
    const colores = datos.map((d) => colorRecuperado(Number(d.recuperado)));

    const opts = baseOptions();
    opts.indexAxis = 'y';
    opts.scales = {
        x: {
            stacked: true,
            max: 100,
            grid: { color: colorRejilla(), drawBorder: false },
            ticks: {
                color: apagado(),
                font: { size: 11, family: FUENTE },
                callback: (v) => v + '%',
            },
            border: { display: false },
        },
        y: {
            stacked: true,
            grid: { display: false },
            ticks: { color: apagado(), font: { size: 11, family: FUENTE } },
            border: { display: false },
        },
    };
    opts.plugins.tooltip.callbacks = {
        title: (items) => datos[items[0].dataIndex]?.nombre || '',
        filter: (item) => item.datasetIndex === 0,
        label: (item) => {
            const d = datos[item.dataIndex];
            return [
                `Recuperado: ${Number(d.recuperado).toFixed(1)}%`,
                `Invertido: ${formatBs(d.invertido)}`,
                `Ingreso: ${formatBs(d.ingreso)}`,
                `Ganancia: ${formatBs(d.ganancia)}`,
            ];
        },
    };

    const etiquetas = {
        id: 'recuperado_' + canvasId,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = `700 10.5px ${FUENTE}`;
            meta.data.forEach((bar, i) => {
                const val = recuperado[i];
                if (val > 10) {
                    ctx.fillStyle = 'rgba(255, 255, 255, .95)';
                    ctx.fillText(`${val.toFixed(0)}%`, bar.x - 14, bar.y);
                }
            });
            ctx.restore();
        },
    };

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Recuperado',
                    data: recuperado,
                    backgroundColor: colores,
                    hoverBackgroundColor: colores.map((c) => lighten(c, 0.15)),
                    borderRadius: 5,
                    borderSkipped: false,
                    barPercentage: 0.7,
                },
                {
                    label: 'Pendiente',
                    data: pendiente,
                    backgroundColor: oscuro ? 'rgba(255,255,255,.08)' : 'rgba(133,141,152,.16)',
                    hoverBackgroundColor: oscuro ? 'rgba(255,255,255,.12)' : 'rgba(133,141,152,.22)',
                    borderRadius: 5,
                    borderSkipped: false,
                    barPercentage: 0.7,
                },
            ],
        },
        options: opts,
        plugins: [etiquetas],
    });
}

// ---------------------------------------------------------------------------
// 5. Evolución diaria — área con degradado
// ---------------------------------------------------------------------------
function initSerieTiempo(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const oscuro = isDark();
    const color = getChartColorsArray(canvasId)?.[0] || cssVar('--estado-ok', '#1baf7a');

    const labels = datos.map((d) => d.etiqueta);
    const values = datos.map((d) => Number(d.valor));
    const ventas = datos.map((d) => d.ventas || '');

    const altura = canvas.parentElement?.offsetHeight || 300;
    const ctx = canvas.getContext('2d');
    const r = parseInt(color.slice(1, 3), 16);
    const g = parseInt(color.slice(3, 5), 16);
    const b = parseInt(color.slice(5, 7), 16);
    const area = (color.startsWith('#') && !Number.isNaN(r))
        ? (() => {
            const grad = ctx.createLinearGradient(0, 0, 0, altura);
            grad.addColorStop(0, `rgba(${r},${g},${b},0.26)`);
            grad.addColorStop(0.75, `rgba(${r},${g},${b},0.03)`);
            return grad;
        })()
        : color;

    const opts = baseOptions();
    opts.interaction = { mode: 'index', intersect: false };
    opts.layout = { padding: { top: 8 } };
    opts.scales.x.ticks = {
        color: apagado(),
        font: { size: 11, family: FUENTE },
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 10,
    };
    opts.scales.y.grid = { color: colorRejilla(), drawBorder: false };
    opts.scales.y.ticks = {
        color: apagado(),
        font: { size: 11, family: FUENTE },
        callback: (v) => formatCompacto(v),
    };
    opts.plugins.tooltip.callbacks = {
        title: (items) => items[0]?.label || '',
        label: (item) => [formatBs(item.raw), ventas[item.dataIndex]].filter(Boolean),
    };

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: values,
                borderColor: color,
                backgroundColor: area,
                borderWidth: 2.5,
                fill: true,
                tension: 0.38,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: color,
                pointHoverBorderColor: oscuro ? '#212529' : '#fff',
                pointHoverBorderWidth: 2,
            }],
        },
        options: opts,
    });
}

// ---------------------------------------------------------------------------
// 6. Ventas: Efectivo vs QR — dona
// ---------------------------------------------------------------------------
function initVentasPago(canvasId, pago) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const efectivo = Number(pago?.[0] ?? 0);
    const qr = Number(pago?.[1] ?? 0);
    const total = efectivo + qr;
    if (total <= 0) return;

    const usar = getChartColorsArray(canvasId)
        || [cssVar('--estado-ok', '#1baf7a'), cssVar('--marca-azul', '#254970')];

    const valores = [
        { nombre: 'Efectivo', valor: efectivo },
        { nombre: 'QR', valor: qr },
    ];

    const opts = baseOptions();
    delete opts.scales;
    opts.cutout = '64%';
    opts.plugins.legend = leyendaTarta(valores, usar);
    opts.plugins.tooltip.callbacks = {
        label: (item) => {
            const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : '0.0';
            return `${formatBs(item.raw)}  ·  ${pct}%`;
        },
    };

    return new Chart(canvas, {
        type: 'pie',
        data: {
            labels: ['Efectivo', 'QR'],
            datasets: [{
                data: [efectivo, qr],
                backgroundColor: usar,
                hoverBackgroundColor: usar.map((c) => lighten(c, 0.12)),
                borderColor: isDark() ? '#212529' : '#fff',
                borderWidth: 3,
            }],
        },
        options: opts,
    });
}

// ---------------------------------------------------------------------------
// 7. Ventas: Evolución diaria Efectivo vs QR — barras apiladas
// ---------------------------------------------------------------------------
function initVentasEvolucion(canvasId, serie) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !serie?.length) return;

    const hasData = serie.some((d) => (Number(d.total) || 0) > 0);
    if (!hasData) return;

    const labels = serie.map((d) => d.etiqueta);
    const valsEfectivo = serie.map((d) => Number(d.efectivo));
    const valsQr = serie.map((d) => Number(d.qr));
    const colores = getChartColorsArray(canvasId);
    const cEf = colores?.[0] || cssVar('--estado-ok', '#1baf7a');
    const cQr = colores?.[1] || cssVar('--marca-azul', '#254970');

    const opts = baseOptions();
    opts.scales.x.stacked = true;
    opts.scales.y.stacked = true;
    opts.scales.y.ticks.callback = (v) => formatCompacto(v);
    opts.plugins.legend = {
        display: true,
        position: 'bottom',
        labels: {
            color: apagado(),
            font: { size: 11.5, family: FUENTE },
            padding: 14,
            usePointStyle: true,
            pointStyleWidth: 9,
            boxHeight: 8,
        },
    };
    opts.plugins.tooltip.callbacks = {
        title: (items) => serie[items[0].dataIndex]?.fecha || '',
        label: (item) => `${item.dataset.label}: ${formatBs(item.raw)}`,
        footer: (items) => 'Total: ' + formatBs(serie[items[0].dataIndex].total),
    };

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Efectivo',
                    data: valsEfectivo,
                    backgroundColor: cEf,
                    borderRadius: 3,
                    barPercentage: 0.7,
                },
                {
                    label: 'QR',
                    data: valsQr,
                    backgroundColor: cQr,
                    borderRadius: 3,
                    barPercentage: 0.7,
                },
            ],
        },
        options: opts,
    });
}

// Exponer funciones globalmente para los @script blocks de Livewire.
window.ReportesCharts = {
    initSerieTiempo,
    initTopProductos,
    initPorVendedor,
    initPorMetodoPago,
    initPorProveedor,
    initVentasPago,
    initVentasEvolucion,
};
