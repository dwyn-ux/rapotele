// E-Raport KumerBot — Service Worker
// Versi cache: update string ini setiap deploy besar agar browser refresh cache
const CACHE_VERSION = 'eraport-v1';
const OFFLINE_PAGE  = '/offline.html';

// Aset yang dicache saat SW di-install
const PRECACHE_ASSETS = [
    OFFLINE_PAGE,
];

// ─── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_ASSETS))
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

    // Jangan cache API, media, atau file dinamis
    const skipPaths = ['/api', '/media.php', '/pwa-icon.php', '/telegram_webhook.php'];
    if (skipPaths.some((p) => url.pathname.startsWith(p))) return;

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
                    return caches.match(OFFLINE_PAGE);
                }
                return new Response('', { status: 503 });
            })
    );
});

// ─── Push Notification ─────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = { title: 'E-Raport', body: 'Ada notifikasi baru.', url: '/', type: 'info' };
    try {
        if (event.data) data = { ...data, ...event.data.json() };
    } catch (e) {}

    const iconMap = {
        absensi: '/pwa-icon.php?size=192',
        jadwal:  '/pwa-icon.php?size=192',
        jurnal:  '/pwa-icon.php?size=192',
        info:    '/pwa-icon.php?size=192',
    };

    const options = {
        body:    data.body,
        icon:    iconMap[data.type] ?? '/pwa-icon.php?size=192',
        badge:   '/pwa-icon.php?size=96',
        tag:     data.tag ?? data.type ?? 'eraport',
        data:    { url: data.url ?? '/' },
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

    const targetUrl = event.notification.data?.url ?? '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Fokus tab yang sudah ada
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
