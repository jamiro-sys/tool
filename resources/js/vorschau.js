/* Vorschau im Tool: PDF (mit pdf.js, funktioniert auch auf dem iPhone) und Bilder */
let pdfjs = null;
let offenesDokument = null; // nicht im Alpine-Store halten: pdf.js nutzt private Felder, die ein Proxy bricht
async function ladePdfjs() {
    if (!pdfjs) {
        const [lib, worker] = await Promise.all([
            import('pdfjs-dist/legacy/build/pdf.mjs'),
            import('pdfjs-dist/legacy/build/pdf.worker.mjs?worker'),
        ]);
        // Worker als normale .js-Datei gebaut (kein .mjs, das manche Server falsch ausliefern)
        lib.GlobalWorkerOptions.workerPort = new worker.default();
        pdfjs = lib;
    }
    return pdfjs;
}

export function vorschauStore(Alpine) {
    Alpine.store('vorschau', {
        offen: false,
        titel: '',
        typ: '',
        url: '',
        download: '',
        laden: false,
        fehler: '',
        seiten: 0,
        lauf: 0,

        async oeffne({ url, titel, typ, download }) {
            Object.assign(this, { offen: true, titel, typ, url, download, fehler: '', seiten: 0, laden: typ === 'pdf' });
            document.documentElement.classList.add('overflow-hidden');
            if (typ === 'pdf') await this.zeichnePdf(++this.lauf);
        },

        schliesse() {
            this.offen = false;
            this.lauf++;
            offenesDokument?.destroy?.();
            offenesDokument = null;
            document.documentElement.classList.remove('overflow-hidden');
            const ziel = document.getElementById('vorschau-seiten');
            if (ziel) ziel.innerHTML = '';
        },

        async zeichnePdf(lauf) {
            const ziel = document.getElementById('vorschau-seiten');
            ziel.innerHTML = '';
            try {
                const lib = await ladePdfjs();
                const dokument = await lib.getDocument({ url: this.url, withCredentials: true }).promise;
                if (lauf !== this.lauf) { dokument.destroy(); return; }
                offenesDokument = dokument;
                this.seiten = dokument.numPages;
                const breite = Math.min(ziel.clientWidth || window.innerWidth, 1100);
                const dichte = Math.min(window.devicePixelRatio || 1, 2);
                for (let n = 1; n <= dokument.numPages; n++) {
                    const seite = await dokument.getPage(n);
                    if (lauf !== this.lauf) return;
                    const basis = seite.getViewport({ scale: 1 });
                    const ansicht = seite.getViewport({ scale: (breite / basis.width) * dichte });
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.floor(ansicht.width);
                    canvas.height = Math.floor(ansicht.height);
                    canvas.style.width = '100%';
                    canvas.className = 'mx-auto block bg-white shadow-none';
                    canvas.setAttribute('aria-label', `Seite ${n} von ${dokument.numPages}`);
                    ziel.appendChild(canvas);
                    await seite.render({ canvasContext: canvas.getContext('2d'), viewport: ansicht }).promise;
                    if (n === 1) this.laden = false;
                }
                this.laden = false;
            } catch (e) {
                if (lauf !== this.lauf) return;
                this.laden = false;
                this.fehler = 'Die Vorschau konnte nicht geladen werden. Du kannst die Datei herunterladen.';
            }
        },
    });
}
