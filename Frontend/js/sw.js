const CACHE_NAME = 'arms-cache-v3';
const urlsToCache = [
  '../index.html',
  '../img/favicon.png',
  '../img/logo.svg'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .catch(err => console.warn('PWA Cache error:', err))
  );
});

self.addEventListener('fetch', event => {
  // Apenas intercetar GET requests. Ignorar API calls.
  if (event.request.method !== 'GET' || event.request.url.includes('/api/')) {
    return;
  }

  // Estratégia Network-First: tenta a rede, se falhar serve do cache
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Resposta válida — guardar uma cópia no cache para fallback offline
        if (response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        // Offline — tentar servir do cache
        return caches.match(event.request);
      })
  );
});

self.addEventListener('activate', event => {
  // Limpar caches antigas ao activar nova versão
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('message', event => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
