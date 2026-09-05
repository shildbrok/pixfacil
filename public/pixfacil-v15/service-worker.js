const PF_CACHE='pixfacil-v15-static-20260904-1531';
const PF_CORE=[
  '/pixfacil-v15/manifest.webmanifest',
  '/pixfacil-v15/pixfacil-v15.css?v=20260904-1531',
  '/pixfacil-v15/pixfacil-v15.js?v=20260904-1531',
  '/pixfacil-v15/logo.svg',
  '/pixfacil-v15/hero.webp',
  '/pixfacil-v15/icons/icon-192.png',
  '/pixfacil-v15/icons/icon-512.png',
  '/pixfacil-v15/avatars/tiger.svg',
  '/pixfacil-v15/avatars/zeus.svg',
  '/pixfacil-v15/avatars/rabbit.svg'
];
self.addEventListener('install',event=>{event.waitUntil(caches.open(PF_CACHE).then(c=>c.addAll(PF_CORE)).catch(()=>{}));self.skipWaiting()});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('pixfacil-v')&&k!==PF_CACHE).map(k=>caches.delete(k)))));self.clients.claim()});
self.addEventListener('fetch',event=>{
  const req=event.request;if(req.method!=='GET')return;
  const url=new URL(req.url);if(url.origin!==self.location.origin)return;
  const path=url.pathname;
  if(path.startsWith('/api/')||path.startsWith('/admin')||path.startsWith('/games/play')||path.startsWith('/retro/play'))return;
  if(req.mode==='navigate'){event.respondWith(fetch(req));return}
  const staticAsset=path.startsWith('/pixfacil-v15/')||path.startsWith('/retro-games/')||path==='/pixfacil-v15/manifest.webmanifest';
  if(!staticAsset)return;
  event.respondWith(caches.match(req,{ignoreSearch:true}).then(hit=>{
    const fresh=fetch(req).then(res=>{if(res&&res.ok){const copy=res.clone();caches.open(PF_CACHE).then(c=>c.put(req,copy)).catch(()=>{})}return res}).catch(()=>hit);
    return hit||fresh;
  }));
});
