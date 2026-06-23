const CACHE_NAME = 'playdf-cache-v3';
const STATIC_CACHE = 'playdf-static-v3';
const PAGES_CACHE = 'playdf-pages-v2';

const STATIC_ASSETS = [
  '/images/icon-192x192.png',
  '/images/icon-512x512.png',
  '/images/logo-playdf.png',
  '/favicon.ico'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(key => key !== STATIC_CACHE && key !== PAGES_CACHE)
            .map(key => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;

  if (url.pathname.startsWith('/build/') || url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/)) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(cache =>
        cache.match(request).then(cached => {
          if (cached) return cached;
          return fetch(request).then(response => {
            if (response.ok) cache.put(request, response.clone());
            return response;
          });
        })
      )
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).then(response => {
        const clone = response.clone();
        caches.open(PAGES_CACHE).then(cache => cache.put(request, clone));
        return response;
      }).catch(() =>
        caches.match(request).then(cached => cached || caches.match('/offline.html'))
      )
    );
    return;
  }

  event.respondWith(
    fetch(request).then(response => {
      if (response.ok && url.origin === self.location.origin) {
        const clone = response.clone();
        caches.open(STATIC_CACHE).then(cache => cache.put(request, clone));
      }
      return response;
    }).catch(() => caches.match(request))
  );
});
