/* Only public assets are cached. Never cache HTML sessions, API data or documents. */
const CACHE='readyfinance-public-v0.3.1';
const ROOT=new URL('./',self.location.href);
const ASSETS=['offline.html','assets/app.css','assets/app.js','assets/imports.js','assets/chart.js','assets/pwa.js','assets/icon.svg','assets/icon-192.png','assets/icon-512.png'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS.map(p=>new Request(new URL(p,ROOT).href,{cache:'reload'}))))));
self.addEventListener('activate',e=>e.waitUntil((async()=>{for(const name of await caches.keys())if(name.startsWith('readyfinance-public-')&&name!==CACHE)await caches.delete(name);await self.clients.claim();})()));
self.addEventListener('fetch',e=>{
 const u=new URL(e.request.url);
 if(e.request.method!=='GET'||u.origin!==ROOT.origin||u.search||!u.pathname.startsWith(ROOT.pathname))return;
 if(e.request.mode==='navigate'){e.respondWith(fetch(e.request).catch(()=>caches.match(new URL('offline.html',ROOT).href)));return;}
 if(ASSETS.some(p=>u.href===new URL(p,ROOT).href))e.respondWith(caches.match(e.request).then(cached=>cached||fetch(e.request)));
});

