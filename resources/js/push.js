/* Push-Benachrichtigungen im Browser ein- und ausschalten */
export function pushVerwaltung(publicKey, aboUrl) {
    return {
        unterstuetzt: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        aktiv: false,
        laedt: false,
        erlaubnis: typeof Notification !== 'undefined' ? Notification.permission : 'default',
        ios: /iPhone|iPad/.test(navigator.userAgent) && !window.matchMedia('(display-mode: standalone)').matches,
        async init() {
            if (!this.unterstuetzt) return;
            const reg = await navigator.serviceWorker.ready;
            this.aktiv = !!(await reg.pushManager.getSubscription());
        },
        async einschalten() {
            this.laedt = true;
            try {
                const erlaubnis = await Notification.requestPermission();
                this.erlaubnis = erlaubnis;
                if (erlaubnis !== 'granted') throw new Error('Benachrichtigungen wurden im Browser nicht erlaubt.');
                const reg = await navigator.serviceWorker.ready;
                const abo = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: schluessel(publicKey) });
                await window.okPost(aboUrl, abo.toJSON());
                this.aktiv = true;
                window.Alpine.store('toast').zeige('Push auf diesem Gerät aktiv.');
            } catch (e) {
                window.Alpine.store('toast').zeige(e.message, 'fehler');
            } finally {
                this.laedt = false;
            }
        },
        async ausschalten() {
            this.laedt = true;
            const reg = await navigator.serviceWorker.ready;
            const abo = await reg.pushManager.getSubscription();
            if (abo) {
                await window.okPost(aboUrl, { endpoint: abo.endpoint }, 'DELETE');
                await abo.unsubscribe();
            }
            this.aktiv = false;
            this.laedt = false;
        },
    };
}

function schluessel(roh0) {
    // Nur das base64url-Alphabet zulassen; ganze Zeilen oder Leerzeichen entfernen
    const base64 = String(roh0 || '').replace(/^\s*VAPID_PUBLIC_KEY\s*=\s*/, '').replace(/[^A-Za-z0-9_\-+/]/g, '');
    if (base64.length < 80) throw new Error('Push ist auf dem Server noch nicht eingerichtet. Ein Admin erzeugt die Schlüssel unter Admin > System.');
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const roh = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...roh].map((c) => c.charCodeAt(0)));
}
