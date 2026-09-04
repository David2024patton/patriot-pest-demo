/*
 * sw.js - Patriot Pest Control service worker.
 * Cached app shell with offline fallback. Versioned cache name: bump
 * CACHE when the shell changes so old entries are retired on activate.
 *
 * Design rules:
 *  - Only GET requests are handled. POST (login, beacon, forms) is never
 *    intercepted, so CSRF and first-party retention flows keep working.
 *  - Navigations are network-first with a cached shell fallback, so a
 *    page that is open when the connection drops still renders.
 *  - Static assets are stale-while-revalidate: fast on repeat visits,
 *    fresh on the next background pass.
 *  - Cross-origin requests (fonts CDN, analytics) pass straight through.
 */
"use strict";

var CACHE = "ppc-shell-v2";

var SHELL = [
  "/",
  "/manifest.webmanifest",
  "/assets/styles.css",
  "/assets/main.js",
  "/assets/pwa-install.css",
  "/assets/pwa-install.js",
  "/assets/icons/icon-192.png",
  "/assets/icons/icon-512.png",
  "/assets/icons/apple-touch-icon.png"
];

self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE).then(function (cache) {
      return cache.addAll(SHELL);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener("activate", function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE; })
            .map(function (key) { return caches.delete(key); })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener("fetch", function (event) {
  var req = event.request;
  if (req.method !== "GET") { return; }

  var url = new URL(req.url);
  if (url.origin !== location.origin) { return; }

  /* Navigations: network first, cached shell fallback for offline. */
  if (req.mode === "navigate") {
    event.respondWith(
      fetch(req).then(function (res) {
        /* Only cache successful HTML responses. */
        if (res.ok) {
          var copy = res.clone();
          caches.open(CACHE).then(function (cache) { cache.put(req.url, copy); });
        }
        return res;
      }).catch(function () {
        return caches.match(req).then(function (cached) {
          return cached || caches.match("/");
        });
      })
    );
    return;
  }

  /* Static assets: stale-while-revalidate. */
  if (/\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|eot|webmanifest)$/.test(url.pathname)) {
    event.respondWith(
      caches.open(CACHE).then(function (cache) {
        return cache.match(req).then(function (cached) {
          var network = fetch(req).then(function (res) {
            if (res.ok) { cache.put(req, res.clone()); }
            return res;
          }).catch(function () { return cached; });
          return cached || network;
        });
      })
    );
    return;
  }
});
