const CACHE_NAME = 'healthdial-v1';
const ASSETS = [
  '/',
  '/index.php',
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/images/icon.png',
  '/assets/images/logo.png'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});

// ===== PUSH NOTIFICATIONS =====
// Receives a signal push (no encrypted payload) from the server.
// Fetches the latest notification content from the API, then displays it.
self.addEventListener('push', e => {
  e.waitUntil(
    fetch('/HealthDial/Backend/api/get_latest_notification.php', { cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        const title = (data && data.title) || 'HealthDial';
        const options = {
          body  : (data && data.message) || 'New health updates available near you.',
          icon  : (data && data.icon)    || '/assets/images/icon.png',
          badge : '/assets/images/icon.png',
          tag   : 'healthdial-push',
          renotify: true,
          data  : { url: (data && data.url) || '/' },
        };
        return self.registration.showNotification(title, options);
      })
      .catch(() => {
        return self.registration.showNotification('HealthDial', {
          body  : 'New health update available. Tap to view.',
          icon  : '/assets/images/icon.png',
          badge : '/assets/images/icon.png',
        });
      })
  );
});

// Focus existing tab or open a new one when notification is clicked
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url.endsWith(url) && 'focus' in client) return client.focus();
      }
      return clients.openWindow(url);
    })
  );
});
