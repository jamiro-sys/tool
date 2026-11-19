import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement, DoughnutController, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

// Styleguide-Farben. Fuer mehrere Reihen werden Abstufungen von Rot und Nachtblau verwendet.
export const farben = {
    rot: '#B8161C', nacht: '#14213D', gelb: '#F5D000', beton: '#5B6472', kreide: '#F6F5F0',
    reihe: ['#B8161C', '#14213D', '#5B6472', '#D4737A', '#6B7A99', '#F5D000', '#A9AEB8', '#E9B9BB'],
};

Chart.defaults.font.family = '"Plus Jakarta Sans", Arial, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = farben.beton;
Chart.defaults.borderColor = 'rgba(20,33,61,0.08)';
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.boxHeight = 10;
Chart.defaults.plugins.tooltip.backgroundColor = farben.nacht;
Chart.defaults.plugins.tooltip.cornerRadius = 0;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 900;
Chart.defaults.maintainAspectRatio = false;

export function diagramm(el, typ, daten) {
    const cfg = { type: 'bar', data: { labels: daten.labels ?? [], datasets: [] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } };

    if (typ === 'balken-gestapelt') {
        cfg.data.datasets = daten.reihen.map((r, i) => ({ label: r.name, data: r.werte, backgroundColor: farben.reihe[i % farben.reihe.length], borderWidth: 0, maxBarThickness: 46 }));
        cfg.options.scales = { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Stunden' } } };
    }

    if (typ === 'balken') {
        cfg.data.datasets = daten.reihen.map((r, i) => ({ label: r.name, data: r.werte, backgroundColor: r.farbe ?? farben.reihe[i % farben.reihe.length], borderWidth: 0, maxBarThickness: 30 }));
        cfg.options.indexAxis = daten.horizontal ? 'y' : 'x';
        cfg.options.scales = { x: { grid: { display: !daten.horizontal }, beginAtZero: true, ticks: { precision: 0 } }, y: { beginAtZero: true, grid: { display: !!daten.horizontal }, ticks: { precision: 0 } } };
        if (daten.reihen.length === 1) cfg.options.plugins.legend.display = false;
    }

    if (typ === 'burndown') {
        cfg.type = 'line';
        cfg.data.datasets = [
            { label: 'Plan (offen laut Termin)', data: daten.soll, borderColor: farben.beton, borderDash: [5, 4], borderWidth: 2, pointRadius: 0, tension: 0 },
            { label: 'Ist (noch offen)', data: daten.ist, borderColor: farben.rot, backgroundColor: 'rgba(184,22,28,0.08)', fill: true, borderWidth: 2.5, pointRadius: 2, pointBackgroundColor: farben.rot, tension: 0.25, spanGaps: false },
        ];
        cfg.options.scales = { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } };
        cfg.options.interaction = { mode: 'index', intersect: false };
    }

    if (typ === 'linie') {
        cfg.type = 'line';
        cfg.data.datasets = [{ label: daten.name, data: daten.werte, borderColor: farben.rot, backgroundColor: farben.rot, borderWidth: 2.5, pointRadius: 3, tension: 0.25, spanGaps: true }];
        cfg.options.scales = { x: { grid: { display: false } }, y: { min: 0, max: 100, ticks: { callback: (v) => v + ' %' } } };
        cfg.options.plugins.legend.display = false;
    }

    if (typ === 'ring') {
        cfg.type = 'doughnut';
        cfg.data.datasets = [{ data: daten.werte, backgroundColor: daten.farben ?? farben.reihe, borderColor: '#fff', borderWidth: 2 }];
        cfg.options.cutout = '72%';
        cfg.options.plugins.legend.position = 'right';
    }

    const chart = new Chart(el, cfg);
    return chart;
}
