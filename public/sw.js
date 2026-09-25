/* Service Worker لمتجر نداف — تخزين مؤقت للعمل بدون اتصال جزئيًا */
const CACHE = 'nadaf-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL]))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    if (url.origin !== location.origin) return;

    // لوحة التحكم وطلبيات Livewire: الشبكة دائمًا
    if (url.pathname.startsWith('/livewire') || url.pathname.startsWith('/admin')) return;

    const isStatic =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/images/') ||
        /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/.test(url.pathname);

    if (isStatic) {
        // ملفات ثابتة: الكاش أولًا مع تحديث بالخلفية
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const cached = await cache.match(event.request);
                const network = fetch(event.request)
                    .then((res) => {
                        if (res.ok) cache.put(event.request, res.clone());
                        return res;
                    })
                    .catch(() => cached);
                return cached || network;
            })
        );
        return;
    }

    // الصفحات: الشبكة أولًا وصفحة عدم الاتصال كبديل
    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    );
});
