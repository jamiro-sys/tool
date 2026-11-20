// Datums- und Zeitfelder im FCM-Stil (flatpickr statt Browser-Standard).
import flatpickr from 'flatpickr';
import { German } from 'flatpickr/dist/l10n/de.js';

flatpickr.localize(German);

const optionen = (el) => {
    const basis = {
        allowInput: false,
        disableMobile: true,
        monthSelectorType: 'static',
        altInput: true,
        altInputClass: el.className + ' feld-datum',
        onChange: (_d, _s, fp) => fp.input.dispatchEvent(new Event('change', { bubbles: true })),
    };
    if (el.type === 'time') {
        return { ...basis, enableTime: true, noCalendar: true, time_24hr: true, dateFormat: 'H:i', altFormat: 'H:i' };
    }
    return { ...basis, dateFormat: 'Y-m-d', altFormat: 'D, j. M Y', locale: { ...German, firstDayOfWeek: 1 } };
};

function einrichten(wurzel = document) {
    wurzel.querySelectorAll('input[type="date"]:not([data-fp]), input[type="time"]:not([data-fp])').forEach((el) => {
        el.dataset.fp = '1';
        const platzhalter = el.getAttribute('placeholder') || (el.type === 'time' ? 'Zeit wählen' : 'Datum wählen');
        const fp = flatpickr(el, optionen(el));
        if (fp.altInput) {
            fp.altInput.placeholder = platzhalter;
            if (el.id) { fp.altInput.id = el.id + '-anzeige'; document.querySelector(`label[for="${el.id}"]`)?.setAttribute('for', fp.altInput.id); }
            if (el.getAttribute('aria-label')) fp.altInput.setAttribute('aria-label', el.getAttribute('aria-label'));
            if (el.required) { fp.altInput.required = true; el.required = false; }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    einrichten();
    // Auch Felder, die spaeter dazukommen (z. B. zusaetzliche Aufgaben im Assistenten)
    new MutationObserver((liste) => liste.forEach((m) => m.addedNodes.forEach((n) => n.nodeType === 1 && einrichten(n.parentNode || n))))
        .observe(document.body, { childList: true, subtree: true });
});
