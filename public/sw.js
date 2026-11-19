/* Service Worker des OK-Tools: Offline-Ansicht und Push */
const CACHE = 'ok-tool-v1';
const OFFLINE = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/img/fcm-logo-negativ.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET' || new URL(req.url).origin !== self.location.origin) return;

    // Seiten: zuerst Netz, sonst die zuletzt gesehene Version, sonst Offline-Seite
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).then((res) => {
                if (res.ok && (new URL(req.url).pathname === '/' )) {
                    const kopie = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, kopie));
                }
                return res;
            }).catch(() => caches.match(req).then((r) => r || caches.match(OFFLINE)))
        );
        return;
    }

    // Gebaute Assets: Cache zuerst
    if (req.url.includes('/build/') || req.url.includes('/img/')) {
        event.respondWith(caches.match(req).then((r) => r || fetch(req).then((res) => {
            const kopie = res.clone();
            caches.open(CACHE).then((c) => c.put(req, kopie));
            return res;
        })));
    }
});

self.addEventListener('push', (event) => {
    let daten = {};
    try { daten = event.data.json(); } catch (e) { daten = { title: 'OK-Tool', body: event.data?.text() }; }
    event.waitUntil(self.registration.showNotification(daten.title || 'OK-Tool', {
        body: daten.body || '',
        icon: '/img/icon-192.png',
        badge: '/img/badge-72.png',
        tag: daten.tag,
        data: { url: daten.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((fenster) => {
        for (const f of fenster) {
            if ('focus' in f) { f.navigate(url); return f.focus(); }
        }
        return self.clients.openWindow(url);
    }));
});
