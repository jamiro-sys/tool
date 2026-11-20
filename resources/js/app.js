import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { diagramm } from './diagramme';
import { pushVerwaltung } from './push';
import './datum';

window.Alpine = Alpine;
window.diagramm = diagramm;
Alpine.plugin(collapse);

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function post(url, daten = {}, methode = 'POST') {
    const antwort = await fetch(url, {
        method: methode,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(daten),
        credentials: 'same-origin',
    });
    if (!antwort.ok) {
        let meldung = 'Das hat nicht geklappt.';
        try { meldung = (await antwort.json()).message || meldung; } catch (e) {}
        throw new Error(meldung);
    }
    return antwort.json();
}
window.okPost = post;

/* Kurze Rueckmeldungen unten am Bildschirm */
Alpine.store('toast', {
    liste: [],
    zeige(text, art = 'ok') {
        const id = Date.now() + Math.random();
        this.liste.push({ id, text, art });
        setTimeout(() => (this.liste = this.liste.filter((t) => t.id !== id)), 3200);
    },
});

/* Aufgabe abhaken (Liste, Board, Start) */
Alpine.data('aufgabeStatus', (url, status) => ({
    status,
    laedt: false,
    get erledigt() { return this.status === 'erledigt'; },
    async setze(neu) {
        if (this.laedt) return;
        const alt = this.status;
        this.status = neu;
        this.laedt = true;
        try {
            const r = await post(url, { status: neu });
            this.status = r.status;
            if (r.status === 'erledigt') Alpine.store('toast').zeige(lobspruch());
            window.dispatchEvent(new CustomEvent('fortschritt', { detail: r.fortschritt }));
        } catch (e) {
            this.status = alt;
            Alpine.store('toast').zeige(e.message, 'fehler');
        } finally {
            this.laedt = false;
        }
    },
    umschalten() { this.setze(this.erledigt ? 'offen' : 'erledigt'); },
}));

/* Nach rechts wischen = erledigt (Handy) */
Alpine.data('wischen', () => ({
    startX: 0, dx: 0, aktiv: false,
    start(e) { this.startX = e.touches[0].clientX; this.aktiv = true; },
    bewegen(e) { if (!this.aktiv) return; this.dx = Math.max(0, Math.min(140, e.touches[0].clientX - this.startX)); },
    ende() {
        if (this.dx > 100) this.$dispatch('gewischt');
        this.dx = 0; this.aktiv = false;
    },
}));

const sprueche = ['Stark, erledigt!', 'Sauber gemacht!', 'Ein Punkt weniger.', 'Weiter so!', 'Tor! Aufgabe erledigt.', 'Das Team dankt dir.'];
function lobspruch() { return sprueche[Math.floor(Math.random() * sprueche.length)]; }

/* Checkliste einer Aufgabe oder Uebergabe */
Alpine.data('checkpunkt', (url, index, erledigt) => ({
    erledigt,
    async umschalten() {
        this.erledigt = !this.erledigt;
        try {
            const r = await post(url, { index });
            window.dispatchEvent(new CustomEvent('checkliste', { detail: r.fortschritt }));
        } catch (e) {
            this.erledigt = !this.erledigt;
            Alpine.store('toast').zeige(e.message, 'fehler');
        }
    },
}));

/* Aktionsmenue, das ueber allem liegt (wird nicht von Karten mit Scrollbereich abgeschnitten) */
Alpine.data('aktionsMenue', () => ({
    m: false,
    lage: '',
    umschalten() {
        if (this.m) { this.m = false; return; }
        const r = this.$refs.knopf.getBoundingClientRect();
        const breite = 240;
        const links = Math.max(8, Math.min(window.innerWidth - breite - 8, r.right - breite));
        const unten = window.innerHeight - r.bottom;
        this.lage = unten > 220 ? `top:${r.bottom + 4}px;left:${links}px` : `bottom:${window.innerHeight - r.top + 4}px;left:${links}px`;
        this.$nextTick(() => { this.m = true; });
    },
    init() { window.addEventListener('scroll', () => { this.m = false; }, { passive: true }); },
}));

/* Dateiablage: Ziehen und Ablegen mehrerer Dateien */
Alpine.data('dateiAblage', () => ({
    art: 'datei',
    ueber: false,
    namen: [],
    gewaehlt() { this.namen = [...this.$refs.eingabe.files].map((f) => f.name); },
    ablegen(e) {
        this.ueber = false;
        const dt = new DataTransfer();
        [...e.dataTransfer.files].forEach((f) => dt.items.add(f));
        this.$refs.eingabe.files = dt.files;
        this.gewaehlt();
    },
}));

/* Haken in der Material-Einsatzliste */
Alpine.data('materialHaken', (url, feld, wert) => ({
    wert,
    async umschalten() {
        this.wert = !this.wert;
        try {
            const r = await post(url, { feld });
            this.wert = r.wert;
        } catch (e) {
            this.wert = !this.wert;
            Alpine.store('toast').zeige(e.message, 'fehler');
        }
    },
}));

/* Zahlen hochzaehlen */
Alpine.data('zaehler', (ziel, dauer = 700) => ({
    wert: 0,
    init() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { this.wert = ziel; return; }
        const start = performance.now();
        const schritt = (t) => {
            const p = Math.min(1, (t - start) / dauer);
            this.wert = Math.round(ziel * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(schritt);
        };
        requestAnimationFrame(schritt);
    },
}));

/* Fortschrittsbalken, der sich beim Laden fuellt */
Alpine.data('balken', (prozent) => ({
    breite: 0,
    init() { requestAnimationFrame(() => setTimeout(() => (this.breite = prozent), 60)); },
}));

/* Glocke mit Ungelesen-Zaehler */
Alpine.data('glocke', (url, anfang) => ({
    anzahl: anfang,
    init() {
        setInterval(async () => {
            if (document.hidden) return;
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' } });
                if (r.ok) this.anzahl = (await r.json()).ungelesen;
            } catch (e) {}
        }, 60000);
    },
}));

/* Schnellsuche (Taste /) */
Alpine.data('suche', (url) => ({
    offen: false, q: '', treffer: [], index: 0, timer: null,
    oeffnen() { this.offen = true; this.$nextTick(() => this.$refs.eingabe.focus()); },
    schliessen() { this.offen = false; this.q = ''; this.treffer = []; },
    tippen() {
        clearTimeout(this.timer);
        this.timer = setTimeout(async () => {
            if (this.q.length < 2) { this.treffer = []; return; }
            const r = await fetch(url + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
            this.treffer = (await r.json()).treffer; this.index = 0;
        }, 180);
    },
    hoch() { this.index = Math.max(0, this.index - 1); },
    runter() { this.index = Math.min(this.treffer.length - 1, this.index + 1); },
    waehlen() { if (this.treffer[this.index]) window.location = this.treffer[this.index].url; },
}));

/* Tastenkuerzel fuer Experten */
document.addEventListener('keydown', (e) => {
    const ziel = e.target;
    if (ziel.closest('input, textarea, select, [contenteditable]') || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.key === '/') { e.preventDefault(); window.dispatchEvent(new CustomEvent('suche-oeffnen')); }
    const kuerzel = document.querySelector(`[data-kuerzel="${e.key}"]`);
    if (kuerzel) { e.preventDefault(); kuerzel.click(); }
});

/* Hinweis nach einem Update */
Alpine.data('neuigkeit', (url) => ({
    sichtbar: true,
    async schliessen() { this.sichtbar = false; try { await post(url); } catch (e) {} },
}));

Alpine.data('pushVerwaltung', pushVerwaltung);

Alpine.start();

/* Service Worker fuer Offline-Ansicht und Push */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
