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
// Resolución de colores desde CSS variables de Velzon
// (mismo patrón que echarts.init.js → getChartColorsArray)
// ---------------------------------------------------------------------------
function getChartColorsArray(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return null;

    const theme = document.documentElement.getAttribute('data-theme') ?? '';
    const attr = theme ? `data-colors-${theme}` : 'data-colors';
    const raw = el.getAttribute(attr) ?? el.getAttribute('data-colors');
    if (!raw) return null;

    try {
        return JSON.parse(raw).map((token) => {
            const t = token.replace(/\s/g, '');
            if (t.indexOf(',') !== -1) {
                const parts = t.split(',');
                const resolved = getComputedStyle(document.documentElement)
                    .getPropertyValue(parts[0]).trim();
                if (parts.length === 2) return `rgba(${resolved},${parts[1]})`;
                return resolved || t;
            }
            return getComputedStyle(document.documentElement)
                .getPropertyValue(t).trim() || t;
        });
    } catch {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------
function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark'
        || window.matchMedia?.('(prefers-color-scheme: dark)').matches;
}

function resolveVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function formatBs(valor) {
    return 'Bs ' + Number(valor).toLocaleString('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function truncar(str, max = 18) {
    if (!str) return '';
    return str.length > max ? str.slice(0, max) + '...' : str;
}

function destroyIfExists(id) {
    const existing = Chart.getChart(id);
    if (existing) existing.destroy();
}

function lighten(hex, amount = 0.15) {
    const c = hex.replace('#', '');
    const r = parseInt(c.substring(0, 2), 16);
    const g = parseInt(c.substring(2, 4), 16);
    const b = parseInt(c.substring(4, 6), 16);
    return `rgb(${Math.min(255, r + (255 - r) * amount)},${Math.min(255, g + (255 - g) * amount)},${Math.min(255, b + (255 - b) * amount)})`;
}

// ---------------------------------------------------------------------------
// Opciones base Velzon
// ---------------------------------------------------------------------------
function baseOptions() {
    const dark = isDark();
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 800, easing: 'easeOutQuart' },
        plugins: {
            legend: { display: false },
            tooltip: {
                enabled: true,
                backgroundColor: dark ? 'rgba(0,0,0,.8)' : 'rgba(255,255,255,.95)',
                titleColor: dark ? '#e2e8f0' : '#495057',
                bodyColor: dark ? '#cbd5e1' : '#495057',
                borderColor: dark ? '#334155' : '#dee2e6',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12,
                titleFont: { size: 13, weight: '600', family: "'Poppins', sans-serif" },
                bodyFont: { size: 13, weight: '500', family: "'Poppins', sans-serif" },
                bodySpacing: 4,
                boxPadding: 4,
                usePointStyle: true,
            },
        },
        scales: {
            x: {
                grid: { color: 'rgba(133, 141, 152, 0.1)', drawBorder: false },
                ticks: { color: '#858d98', font: { size: 12, family: "'Poppins', sans-serif" } },
                border: { display: false },
            },
            y: {
                grid: { display: false },
                ticks: { color: '#858d98', font: { size: 12, family: "'Poppins', sans-serif" } },
                border: { display: false },
            },
        },
    };
}

// ---------------------------------------------------------------------------
// 1. Productos más vendidos — Bar Label (vertical, con label en cada barra)
//    Patrón: chart-bar-label-rotation de Velzon
// ---------------------------------------------------------------------------
function initTopProductos(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const colors = getChartColorsArray(canvasId);
    const color = colors?.[0] || resolveVar('--vz-primary') || '#40518e';
    const colorHover = lighten(color, 0.15);

    const labels = datos.map(d => truncar(d.nombre, 14));
    const values = datos.map(d => Number(d.ingreso));
    const metas = datos.map(d => `${d.unidades} ${d.unidades == 1 ? 'un.' : 'un.'} · ${d.sku}`);

    const opts = baseOptions();

    // Labels encima de cada barra (patrón Bar Label de Velzon)
    opts.plugins.tooltip.callbacks = {
        title: (items) => datos[items[0].dataIndex]?.nombre || '',
        label: (item) => formatBs(item.raw),
        afterLabel: (item) => metas[item.dataIndex] || '',
    };

    // Plugin para label encima de cada barra
    const barLabelPlugin = {
        id: 'barLabel_' + canvasId,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.font = "500 11px 'Poppins', sans-serif";
            ctx.fillStyle = '#858d98';
            meta.data.forEach((bar, i) => {
                const val = values[i];
                ctx.fillText(formatBs(val), bar.x, bar.y - 4);
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
                hoverBackgroundColor: colorHover,
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.65,
            }],
        },
        options: opts,
        plugins: [barLabelPlugin],
    });
}

// ---------------------------------------------------------------------------
// 2. Ventas por vendedor — Bar Label (vertical, con label en cada barra)
//    Patrón: chart-bar-label-rotation de Velzon
// ---------------------------------------------------------------------------
function initPorVendedor(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const colors = getChartColorsArray(canvasId);
    const color = colors?.[0] || resolveVar('--vz-success') || '#0acf97';
    const colorHover = lighten(color, 0.15);

    const labels = datos.map(d => truncar(d.name, 14));
    const values = datos.map(d => Number(d.ingreso));
    const ventas = datos.map(d => `${d.ventas} ${d.ventas == 1 ? 'venta' : 'ventas'}`);

    const opts = baseOptions();

    opts.plugins.tooltip.callbacks = {
        title: (items) => datos[items[0].dataIndex]?.name || '',
        label: (item) => formatBs(item.raw),
        afterLabel: (item) => ventas[item.dataIndex] || '',
    };

    const barLabelPlugin = {
        id: 'barLabel_' + canvasId,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.font = "500 11px 'Poppins', sans-serif";
            ctx.fillStyle = '#858d98';
            meta.data.forEach((bar, i) => {
                ctx.fillText(formatBs(values[i]), bar.x, bar.y - 4);
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
                hoverBackgroundColor: colorHover,
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.65,
            }],
        },
        options: opts,
        plugins: [barLabelPlugin],
    });
}

// ---------------------------------------------------------------------------
// 3. Cómo se cobró — Pie Charts (radio 50%, sin dona)
//    Patrón: chart-pie de Velzon
// ---------------------------------------------------------------------------
function initPorMetodoPago(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const paleta = [
        resolveVar('--vz-primary') || '#40518e',
        resolveVar('--vz-success') || '#0acf97',
        resolveVar('--vz-warning') || '#fabc3d',
        resolveVar('--vz-danger')  || '#f06548',
        resolveVar('--vz-info')    || '#39afd1',
    ];

    const colors = getChartColorsArray(canvasId);
    const coloresUsar = colors || paleta;

    const labels = datos.map(d => d.nombre);
    const values = datos.map(d => Number(d.ingreso));
    const total = values.reduce((a, b) => a + b, 0);

    const opts = baseOptions();
    delete opts.scales;

    // Pie de Velzon: tooltip por item, leyenda vertical a la izquierda
    opts.plugins.legend = {
        display: true,
        position: 'bottom',
        labels: {
            color: '#858d98',
            font: { size: 12, family: "'Poppins', sans-serif" },
            padding: 16,
            usePointStyle: true,
            pointStyleWidth: 10,
            generateLabels(chart) {
                const data = chart.data;
                if (!data.labels?.length) return [];
                return data.labels.map((label, i) => ({
                    text: `${label} — ${formatBs(values[i])}`,
                    fillStyle: coloresUsar[i % coloresUsar.length],
                    strokeStyle: 'transparent',
                    pointStyle: 'circle',
                    hidden: false,
                    index: i,
                }));
            },
        },
    };

    opts.plugins.tooltip.callbacks = {
        title: (items) => datos[items[0].dataIndex]?.nombre || '',
        label: (item) => {
            const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : '0.0';
            return `${formatBs(item.raw)}  ·  ${pct}%`;
        },
    };

    return new Chart(canvas, {
        type: 'pie',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: datos.map((_, i) => coloresUsar[i % coloresUsar.length]),
                hoverBackgroundColor: datos.map((_, i) => lighten(coloresUsar[i % coloresUsar.length], 0.12)),
                borderColor: isDark() ? '#1e1e2e' : '#fff',
                borderWidth: 2,
            }],
        },
        options: opts,
    });
}

// ---------------------------------------------------------------------------
// 4. Rentabilidad por proveedor — Stacked Horizontal Bar
//    Patrón: chart-horizontal-bar-stacked de Velzon
//    Cada proveedor muestra una barra apilada: [recuperado, pendiente]
//    con color según % (verde ≥100%, ámbar 50-99%, rojo <50%)
// ---------------------------------------------------------------------------
function colorRecuperado(pct) {
    if (pct >= 100) return resolveVar('--vz-success') || '#0acf97';
    if (pct >= 50)  return resolveVar('--vz-warning') || '#fabc3d';
    return resolveVar('--vz-danger') || '#f06548';
}

function initPorProveedor(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const dark = isDark();
    const labels = datos.map(d => truncar(d.nombre, 20));
    const recuperado = datos.map(d => Math.min(Number(d.recuperado), 100));
    const pendiente = datos.map(d => Math.max(100 - Math.min(Number(d.recuperado), 100), 0));
    const colores = datos.map(d => colorRecuperado(Number(d.recuperado)));

    const opts = baseOptions();

    // Eje X como porcentaje (0–100)
    opts.scales.x = {
        ...opts.scales.x,
        max: 100,
        ticks: {
            ...opts.scales.x.ticks,
            callback: (v) => v + '%',
        },
        grid: { color: 'rgba(133, 141, 152, 0.1)', drawBorder: false },
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

    // Plugin para label "XX%" dentro de la barra
    const stackedLabelPlugin = {
        id: 'stackedLabel_' + canvasId,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = "600 11px 'Poppins', sans-serif";
            meta.data.forEach((bar, i) => {
                const val = recuperado[i];
                if (val > 8) {
                    ctx.fillStyle = dark ? '#1e1e2e' : '#fff';
                    ctx.fillText(`${val.toFixed(0)}%`, bar.x, bar.y);
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
                    hoverBackgroundColor: colores.map(c => lighten(c, 0.15)),
                    borderRadius: 4,
                    borderSkipped: false,
                    barPercentage: 0.65,
                },
                {
                    label: 'Pendiente',
                    data: pendiente,
                    backgroundColor: dark ? 'rgba(255,255,255,.06)' : 'rgba(133,141,152,.12)',
                    hoverBackgroundColor: 'transparent',
                    borderRadius: 4,
                    borderSkipped: false,
                    barPercentage: 0.65,
                },
            ],
        },
        options: opts,
        plugins: [stackedLabelPlugin],
    });
}

// ---------------------------------------------------------------------------
// 5. Evolución diaria — Line/Area chart
//    Patrón: chart-line de Velzon (con área degradada)
// ---------------------------------------------------------------------------
function initSerieTiempo(canvasId, datos) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !datos?.length) return;

    const dark = isDark();
    const color = getChartColorsArray(canvasId)?.[0]
        || resolveVar('--vz-success')
        || '#0acf97';

    const labels = datos.map(d => d.etiqueta);
    const values = datos.map(d => Number(d.valor));
    const ventas = datos.map(d => d.ventas || '');

    // Degradado del área
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentElement?.offsetHeight || 300);
    gradient.addColorStop(0, color.replace(')', ',0.25)').replace('rgb', 'rgba'));
    gradient.addColorStop(0.8, color.replace(')', ',0.02)').replace('rgb', 'rgba'));

    // Si el color es hex, convertir a rgba para el gradiente
    let areaBg = gradient;
    if (color.startsWith('#')) {
        const r = parseInt(color.slice(1, 3), 16);
        const g = parseInt(color.slice(3, 5), 16);
        const b = parseInt(color.slice(5, 7), 16);
        const g2 = ctx.createLinearGradient(0, 0, 0, canvas.parentElement?.offsetHeight || 300);
        g2.addColorStop(0, `rgba(${r},${g},${b},0.25)`);
        g2.addColorStop(0.8, `rgba(${r},${g},${b},0.02)`);
        areaBg = g2;
    }

    const opts = baseOptions();
    opts.interaction = { mode: 'index', intersect: false };
    opts.plugins.legend = { display: false };
    opts.plugins.tooltip.callbacks = {
        title: (items) => items[0]?.label || '',
        label: (item) => {
            const v = ventas[item.dataIndex];
            return [
                formatBs(item.raw),
                v ? `${v}` : '',
            ].filter(Boolean);
        },
    };

    opts.scales.x.grid = { display: false };
    opts.scales.x.ticks = {
        color: '#858d98',
        font: { size: 11, family: "'Poppins', sans-serif" },
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 10,
    };
    opts.scales.y.grid = {
        color: 'rgba(133, 141, 152, 0.1)',
        drawBorder: false,
    };
    opts.scales.y.ticks = {
        color: '#858d98',
        font: { size: 11, family: "'Poppins', sans-serif" },
        callback: (v) => {
            if (v >= 1000) return (v / 1000).toFixed(v >= 10000 ? 0 : 1) + 'k';
            return v;
        },
    };

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: values,
                borderColor: color,
                backgroundColor: areaBg,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: color,
                pointHoverBorderColor: dark ? '#1e1e2e' : '#fff',
                pointHoverBorderWidth: 2,
            }],
        },
        options: opts,
    });
}

// ---------------------------------------------------------------------------
// 6. Ventas: Ingresos por tipo de cobro (Efectivo vs QR) — Pie
//    Efectivo y QR puros + mixto desagregado en sus partes.
// ---------------------------------------------------------------------------
function initVentasPago(canvasId, pago) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const efectivo = Number(pago?.[0] ?? 0);
    const qr = Number(pago?.[1] ?? 0);
    const total = efectivo + qr;
    if (total <= 0) return;

    const colores = [
        resolveVar('--vz-success') || '#0acf97',
        resolveVar('--vz-primary') || '#40518e',
    ];
    const resolved = getChartColorsArray(canvasId);
    const usar = resolved?.length >= 2 ? resolved : colores;

    const opts = baseOptions();
    delete opts.scales;
    opts.plugins.legend = {
        display: true,
        position: 'bottom',
        labels: {
            color: '#858d98',
            font: { size: 12, family: "'Poppins', sans-serif" },
            padding: 16,
            usePointStyle: true,
            pointStyleWidth: 10,
            generateLabels(chart) {
                const vals = [efectivo, qr];
                const noms = ['Efectivo', 'QR'];
                return noms.map((label, i) => ({
                    text: `${label} — ${formatBs(vals[i])}`,
                    fillStyle: usar[i % usar.length],
                    strokeStyle: 'transparent',
                    pointStyle: 'circle',
                    hidden: false,
                    index: i,
                }));
            },
        },
    };
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
                hoverBackgroundColor: usar.map(c => lighten(c, 0.12)),
                borderColor: isDark() ? '#1e1e2e' : '#fff',
                borderWidth: 2,
            }],
        },
        options: opts,
    });
}

// ---------------------------------------------------------------------------
// 7. Ventas: Evolución diaria Efectivo vs QR — Stacked Bar
// ---------------------------------------------------------------------------
function initVentasEvolucion(canvasId, serie) {
    destroyIfExists(canvasId);
    const canvas = document.getElementById(canvasId);
    if (!canvas || !serie?.length) return;
    const hasData = serie.some(d => (Number(d.total) || 0) > 0);
    if (!hasData) return;

    const labels = serie.map(d => d.etiqueta);
    const valsEfectivo = serie.map(d => Number(d.efectivo));
    const valsQr = serie.map(d => Number(d.qr));
    const colores = getChartColorsArray(canvasId);
    const cEf = colores?.[0] || resolveVar('--vz-success') || '#0acf97';
    const cQr = colores?.[1] || resolveVar('--vz-primary') || '#40518e';

    const opts = baseOptions();
    opts.scales.x.stacked = true;
    opts.scales.y.stacked = true;
    opts.scales.y.ticks.callback = (v) => {
        if (v >= 1000) return (v/1000).toFixed(v >= 10000 ? 0 : 1) + 'k';
        return v;
    };
    opts.plugins.legend = {
        display: true,
        position: 'bottom',
        labels: {
            color: '#858d98',
            font: { size: 12, family: "'Poppins', sans-serif" },
            padding: 16,
            usePointStyle: true,
            pointStyleWidth: 10,
        },
    };
    opts.plugins.tooltip.callbacks = {
        title: (items) => serie[items[0].dataIndex]?.fecha || '',
        label: (item) => `${item.dataset.label}: ${formatBs(item.raw)}`,
        footer: (items) => {
            const idx = items[0].dataIndex;
            return 'Total: ' + formatBs(serie[idx].total);
        },
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
