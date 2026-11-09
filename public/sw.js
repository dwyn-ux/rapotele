// E-Raport KumerBot — Service Worker
// Versi cache: update string ini setiap deploy besar agar browser refresh cache
const CACHE_VERSION = 'eraport-v2';
const OFFLINE_PAGE  = new URL('offline.html', self.location.href).href;

// Aset yang dicache saat SW di-install
const PRECACHE_ASSETS = [
    OFFLINE_PAGE,
];

// ─── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then(async (cache) => {
            try {
                await cache.addAll(PRECACHE_ASSETS);
            } catch (err) {
                console.warn('Precache non-critical notice:', err);
            }
        })
    );
    self.skipWaiting();
});

// ─── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k !== CACHE_VERSION)
                    .map((k) => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// ─── Fetch (Network-first, offline fallback) ───────────────────────────────────
self.addEventListener('fetch', (event) => {
    // Hanya handle GET dari domain yang sama
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;

    // Jangan cache API, media, icon dinamis, atau webhook
    const skipPaths = ['/api', '/media.php', '/pwa-icon.php', '/telegram_webhook.php', '/manifest.php'];
    if (skipPaths.some((p) => url.pathname.includes(p))) return;

    event.respondWith(
        fetch(event.request)
            .then((res) => {
                // Cache halaman HTML yang berhasil diambil
                if (res.ok && event.request.headers.get('accept')?.includes('text/html')) {
                    const clone = res.clone();
                    caches.open(CACHE_VERSION).then((c) => c.put(event.request, clone));
                }
                return res;
            })
            .catch(async () => {
                // Offline: coba dari cache, fallback ke offline.html
                const cached = await caches.match(event.request);
                if (cached) return cached;
                if (event.request.headers.get('accept')?.includes('text/html')) {
                    const offline = await caches.match(OFFLINE_PAGE);
                    if (offline) return offline;
                }
                return new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
            })
    );
});

// ─── Push Notification ─────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    const defaultUrl = new URL('index.php', self.location.href).href;
    const icon192    = new URL('pwa-icon.php?size=192', self.location.href).href;
    const icon96     = new URL('pwa-icon.php?size=96', self.location.href).href;

    let data = { title: 'E-Raport', body: 'Ada notifikasi baru.', url: defaultUrl, type: 'info' };
    try {
        if (event.data) data = { ...data, ...event.data.json() };
    } catch (e) {}

    const targetUrl = data.url ? new URL(data.url, self.location.href).href : defaultUrl;

    const options = {
        body:    data.body,
        icon:    icon192,
        badge:   icon96,
        tag:     data.tag ?? data.type ?? 'eraport',
        data:    { url: targetUrl },
        vibrate: [200, 100, 200],
        requireInteraction: data.type === 'absensi', // absensi butuh interaksi user
        actions: buildActions(data.type),
    };

    event.waitUntil(self.registration.showNotification(data.title, options));
});

function buildActions(type) {
    if (type === 'absensi') {
        return [
            { action: 'open',    title: '✅ Isi Absensi' },
            { action: 'dismiss', title: '✖ Nanti' },
        ];
    }
    if (type === 'jadwal') {
        return [
            { action: 'open',    title: '📚 Lihat Jadwal' },
            { action: 'dismiss', title: '✖ Tutup' },
        ];
    }
    if (type === 'jurnal') {
        return [
            { action: 'open',    title: '📝 Isi Jurnal' },
            { action: 'dismiss', title: '✖ Nanti' },
        ];
    }
    return [];
}

// ─── Notification Click ────────────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    if (event.action === 'dismiss') return;

    const defaultUrl = new URL('index.php', self.location.href).href;
    const targetUrl  = event.notification.data?.url ? new URL(event.notification.data.url, self.location.href).href : defaultUrl;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Fokus tab yang sudah ada jika ada
            for (const client of windowClients) {
                if (client.url.includes(self.location.origin)) {
                    client.focus();
                    client.navigate(targetUrl);
                    return;
                }
            }
            // Buka tab baru jika tidak ada
            return clients.openWindow(targetUrl);
        })
    );
});

// ─── Background Sync (opsional, untuk offline form submit) ────────────────────
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-absensi') {
        // Placeholder: sinkronisasi data absensi yang gagal saat offline
        event.waitUntil(Promise.resolve());
    }
});
