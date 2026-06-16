const CACHE_NAME = 'playdf-cache-v1';

self.addEventListener('install', (event) => {
    console.log('Service Worker instalado');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('Service Worker activado');
});

// Interceptar peticiones para que el navegador reconozca el PWA
self.addEventListener('fetch', (event) => {
    // Por ahora, solo dejamos que las peticiones pasen normalmente
    // No romperemos ninguna vista Blade ni llamadas a la IA
    event.respondWith(fetch(event.request));
});