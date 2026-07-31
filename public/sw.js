/*
 * Service Worker المنصة (بند ١٥ — PWA).
 *
 * الدور محدود عمدًا: تثبيت اللوحة على الجوال (أهمها iOS حيث لا تطبيق)،
 * وصفحة سقوط اتصال مهذبة، وكاش لأصول البناء المبصومة. لا كاش لأي HTML
 * تطبيقي — بيانات التشخيص تُقرأ حية دائمًا.
 */
const CACHE = 'ks-static-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL])),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // أصول البناء مبصومة بالمحتوى — كاش أولًا بأمان.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached
                || fetch(request).then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                    return response;
                })),
        );
        return;
    }

    // تنقّل صفحات: شبكة دائمًا، وعند الانقطاع صفحة offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
    }
});
