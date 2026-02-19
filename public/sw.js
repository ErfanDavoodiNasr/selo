const CACHE_PREFIX = 'selo-static';
const CACHE_VERSION = '2026-02-19-1';
const CACHE_NAME = `${CACHE_PREFIX}-${CACHE_VERSION}`;

function scopeBasePath() {
  const scopePath = new URL(self.registration.scope).pathname;
  if (scopePath === '/') return '';
  return scopePath.endsWith('/') ? scopePath.slice(0, -1) : scopePath;
}

const BASE_PATH = scopeBasePath();

function withBase(path) {
  return `${BASE_PATH}${path}`;
}

function toScopedPath(pathname) {
  if (!BASE_PATH) {
    return pathname;
  }
  if (pathname === BASE_PATH) {
    return '/';
  }
  if (pathname.startsWith(`${BASE_PATH}/`)) {
    return pathname.slice(BASE_PATH.length);
  }
  return null;
}

const APP_SHELL = withBase('/');
const PRECACHE_URLS = [
  APP_SHELL,
  withBase('/assets/css/fonts.css'),
  withBase('/assets/style.css'),
  withBase('/assets/css/app.css'),
  withBase('/assets/emoji-picker.js'),
  withBase('/assets/app.js'),
  withBase('/assets/vendor/fonts/vazirmatn-arabic.woff2'),
  withBase('/assets/vendor/fonts/vazirmatn-latin.woff2'),
  withBase('/assets/vendor/fonts/material-symbols-rounded.woff2')
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys
        .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
        .map((key) => caches.delete(key))
    )).then(() => self.clients.claim())
  );
});

async function cacheFirst(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request, { ignoreSearch: true });
  if (cached) {
    return cached;
  }
  const network = await fetch(request);
  if (network && network.ok) {
    cache.put(request, network.clone());
  }
  return network;
}

async function networkFirst(request, fallbackUrl) {
  const cache = await caches.open(CACHE_NAME);
  try {
    const network = await fetch(request);
    if (network && network.ok) {
      cache.put(request, network.clone());
    }
    return network;
  } catch (err) {
    const cached = await cache.match(request, { ignoreSearch: true });
    if (cached) {
      return cached;
    }
    if (fallbackUrl) {
      const fallback = await cache.match(fallbackUrl, { ignoreSearch: true });
      if (fallback) {
        return fallback;
      }
    }
    throw err;
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const scopedPath = toScopedPath(url.pathname);
  if (scopedPath === null) return;

  if (scopedPath.startsWith('/api/') || scopedPath === '/photo.php') {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request, APP_SHELL));
    return;
  }

  if (scopedPath.startsWith('/assets/') || scopedPath === '/sw.js') {
    event.respondWith(cacheFirst(request));
    return;
  }

  event.respondWith(
    fetch(request).catch(async () => {
      const cache = await caches.open(CACHE_NAME);
      return cache.match(request, { ignoreSearch: true });
    })
  );
});
