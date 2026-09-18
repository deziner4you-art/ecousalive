/*
=====================================================
ECO A+ PRO — Service Worker
Minimal offline shell. Caches the app shell so
the UI loads instantly on repeat visits.
=====================================================
*/

var CACHE_NAME    = 'eco-aplus-v23';
/* Paths relative to the SW scope (the public/ directory) */
var SHELL_ASSETS  = [
    './',
    './css/app.css',
    './js/core.js',
    './js/tasks.js',
    './js/admin.js',
    './js/invoices.js',
    './js/payroll.js',
    './js/pwa.js',
    './assets/icon-192.png',
];

/* Install: cache shell assets */
self.addEventListener('install', function(event){
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache){
            return cache.addAll(SHELL_ASSETS);
        })
    );
    self.skipWaiting();
});

/* Activate: remove old caches */
self.addEventListener('activate', function(event){
    event.waitUntil(
        caches.keys().then(function(keys){
            return Promise.all(
                keys.filter(function(k){ return k !== CACHE_NAME; })
                    .map(function(k){ return caches.delete(k); })
            );
        })
    );
    self.clients.claim();
});

/* Fetch: network-first for API, cache-first for assets */
self.addEventListener('fetch', function(event){
    var url = new URL(event.request.url);

    /* Always go to network for API actions */
    if(url.searchParams.has('action') || event.request.method === 'POST'){
        event.respondWith(fetch(event.request));
        return;
    }

    /* Cache-first for static assets */
    event.respondWith(
        caches.match(event.request).then(function(cached){
            return cached || fetch(event.request).then(function(response){
                if(response && response.status === 200 && response.type === 'basic'){
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache){
                        cache.put(event.request, clone);
                    });
                }
                return response;
            });
        }).catch(function(){
            /* Offline fallback for navigation requests */
            if(event.request.mode === 'navigate'){
                return caches.match('./');
            }
        })
    );
});
