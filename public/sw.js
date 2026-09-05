/* ──────────────────────────────────────────────────────────────────────
 *  Service Worker — Central iGaming
 *  Estrategia SEGURA (nao "prende" cache velho):
 *   - HTML / navegacao: SEMPRE rede (network-first). Nunca serve pagina velha.
 *   - /build/assets/* (arquivos com hash, imutaveis): cache-first (rapido e seguro,
 *     pois todo novo build gera hash novo = entrada nova no cache).
 *   - API e o resto: passa direto pela rede (nao cacheia).
 *   - skipWaiting + clients.claim => atualizacao entra na hora.
 *   - No activate, limpa caches de versoes antigas.
 *
 *  Para DESLIGAR: remova o public/sw.js e o registro no app.js, ou rode no console:
 *    navigator.serviceWorker.getRegistrations().then(r=>r.forEach(x=>x.unregister()))
 * ────────────────────────────────────────────────────────────────────── */

const VERSION = 'v1';
const ASSET_CACHE = 'ci-assets-' + VERSION;

self.addEventListener('install', () => {
    // Ativa a nova versao imediatamente, sem esperar abas antigas fecharem.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(
            keys.filter((k) => k !== ASSET_CACHE).map((k) => caches.delete(k))
        );
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // So GET; POST/PUT/etc. sempre pela rede.
    if (req.method !== 'GET') return;

    let url;
    try { url = new URL(req.url); } catch (e) { return; }

    // So mesma origem.
    if (url.origin !== self.location.origin) return;

    // 1) Documentos HTML / navegacao => network-first (pagina sempre fresca).
    if (req.mode === 'navigate' || req.destination === 'document') {
        event.respondWith(
            fetch(req).catch(() => caches.match(req))
        );
        return;
    }

    // 2) Assets com hash (imutaveis) => cache-first.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith((async () => {
            const cache = await caches.open(ASSET_CACHE);
            const hit = await cache.match(req);
            if (hit) return hit;
            try {
                const res = await fetch(req);
                if (res && res.ok) cache.put(req, res.clone());
                return res;
            } catch (e) {
                return hit || Response.error();
            }
        })());
        return;
    }

    // 3) API e demais => rede direta (sem cache). Deixa o browser tratar.
});
