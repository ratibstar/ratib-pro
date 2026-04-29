const CACHE_NAME = 'worker-tracker-v17';
const APP_SHELL = [
  '/mobile-app/index.php',
  '/mobile-app/index.html',
  '/mobile-app/assets/css/app.css?v=1',
  '/mobile-app/assets/js/app.js?v=18',
  '/mobile-app/manifest.json',
  '/mobile-app/assets/icons/icon-192.svg',
  '/mobile-app/assets/icons/icon-512.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET') return;

  if (url.pathname.startsWith('/api/worker-tracking/')) {
    event.respondWith(networkFirst(req));
    return;
  }

  event.respondWith(cacheFirst(req));
});

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const fresh = await fetch(request);
    const cache = await caches.open(CACHE_NAME);
    cache.put(request, fresh.clone());
    return fresh;
  } catch (e) {
    return caches.match('/mobile-app/index.php');
  }
}

async function networkFirst(request) {
  try {
    const fresh = await fetch(request);
    return fresh;
  } catch (e) {
    const cached = await caches.match(request);
    return cached || new Response(JSON.stringify({ success: false, message: 'Offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

self.addEventListener('sync', (event) => {
  if (event.tag === 'worker-location-sync') {
    event.waitUntil(flushQueue());
  }
});

async function flushQueue() {
  const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
  await Promise.all(
    clients.map((client) => client.postMessage({ type: 'SW_SYNC_REQUEST' }))
  );
}

