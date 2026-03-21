// ProWay Lab — Service Worker
// Cache-first for static assets, network-first for API, offline fallback.

const CACHE_VERSION = 'pw-v1';
const STATIC_CACHE = 'pw-static-' + CACHE_VERSION;
const RUNTIME_CACHE = 'pw-runtime-' + CACHE_VERSION;

// Assets to pre-cache on install
const PRECACHE_URLS = [
  '/offline.html',
  '/images/imagotipo-proway-blanco.png',
  '/images/pwa-icon-192.png',
];

// ── Install: pre-cache essential assets ──────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

// ── Activate: purge old caches ───────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => k !== STATIC_CACHE && k !== RUNTIME_CACHE)
          .map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// ── Fetch strategy ───────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') return;

  // Skip cross-origin requests
  if (url.origin !== self.location.origin) return;

  // Network-first for API calls and PHP pages that need fresh data
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(request));
    return;
  }

  // Cache-first for static assets (CSS, JS, images, fonts)
  if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Network-first for HTML pages, with offline fallback
  event.respondWith(networkFirstWithOfflineFallback(request));
});

// ── Helpers ──────────────────────────────────────────────────────────────────

function isStaticAsset(pathname) {
  return /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|webp)(\?.*)?$/.test(pathname)
    || pathname.startsWith('/dist/');
}

// Try cache first, fall back to network (for static assets)
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (_) {
    return new Response('', { status: 503, statusText: 'Offline' });
  }
}

// Try network first, fall back to cache (for API calls)
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (_) {
    const cached = await caches.match(request);
    return cached || new Response(JSON.stringify({ success: false, error: 'Offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

// Try network first, fall back to offline page (for navigation)
async function networkFirstWithOfflineFallback(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (_) {
    const cached = await caches.match(request);
    if (cached) return cached;

    // Navigation request → show offline page
    if (request.mode === 'navigate') {
      return caches.match('/offline.html');
    }

    return new Response('', { status: 503, statusText: 'Offline' });
  }
}
