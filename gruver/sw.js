const CACHE_NAME = 'gruvekart-v4';
const ASSETS_TO_CACHE = [
    './',
    'index.php',
    'manifest.json',
    'assets/app-icon-192.png',
    'assets/app-icon-512.png',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;600&display=swap'
];

// Install Service Worker
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            // We don't want to fail install if some external asset fails, so we handle them
            return cache.addAll(ASSETS_TO_CACHE).catch(err => console.warn('Failed to cache some assets', err));
        })
    );
    self.skipWaiting();
});

// Activate Service Worker
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch Strategy: Network First for content, Cache First for static assets
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // For tiles and map data, use Network Only (don't cache huge map tiles)
    if (url.pathname.includes('/wmts/') || url.pathname.includes('/wms')) {
        return;
    }

    // For static assets (images, css, js), try Cache first
    if (event.request.destination === 'image' || event.request.destination === 'style' || event.request.destination === 'script') {
        event.respondWith(
            caches.match(event.request).then((response) => {
                return response || fetch(event.request);
            })
        );
        return;
    }

    // For everything else (HTML, main app), try Network first, then Cache
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Update cache with new version
                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, responseClone);
                });
                return response;
            })
            .catch(() => {
                return caches.match(event.request);
            })
    );
});
