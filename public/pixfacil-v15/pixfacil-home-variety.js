(()=>{'use strict';
const isHome=()=>location.pathname.replace(/\/+$/,'')===''||location.pathname==='/';
const isDesktop=()=>window.innerWidth>=768;
let lastHref=location.href,loading=false,lastRun=0;
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function asset(v){if(!v)return '';v=String(v).trim();if(/^(?:https?:|data:|blob:)/i.test(v))return v;if(v.startsWith('/'))return v;v=v.replace(/^\.\//,'').replace(/^public\//,'');if(v.startsWith('storage/'))return '/'+v;if(v.startsWith('uploads/'))return '/storage/'+v;return '/storage/'+v}
function slug(v){return String(v||'jogo').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')||'jogo'}
function hrefFor(section){if(section?.type==='category'&&section.slug)return '/casino/provider/all/category/'+encodeURIComponent(section.slug);if(section?.type==='new')return '/casino/provider/all/category/all?sort=new';if(section?.type==='popular')return '/casino/provider/all/category/all?sort=popular';return '/casino/provider/all/category/all'}
function gameCard(g){const name=g?.game_name||g?.name||g?.game_code||'Jogo',img=asset(g?.cover);return `<a class="pfdh-game" href="/games/play/${encodeURIComponent(g.id)}/${encodeURIComponent(slug(name))}" data-pf-extra-game="${esc(g.id)}"><span class="pfdh-game-art">${img?`<img src="${esc(img)}" loading="lazy" alt="">`:''}</span><strong>${esc(name)}</strong><small>${esc(g?.provider||'')}</small></a>`}
function visibleIds(root){const set=new Set();for(const a of root.querySelectorAll('a[href*="/games/play/"]')){const m=(a.getAttribute('href')||'').match(/\/games\/play\/([^/]+)/i);if(m)set.add(String(m[1]))}return set}
function existingTitles(root){return new Set([...root.querySelectorAll('.pfdh-section-head h2')].map(x=>(x.textContent||'').trim().toLowerCase()))}
async function hydrate(force=false){
 if(!isHome()||!isDesktop())return;
 const main=document.querySelector('#pfd-home .pfdh-main');if(!main||loading)return;
 const old=main.querySelector('[data-pfd-extra-sections]');if(old&&!force)return;if(old)old.remove();
 const now=Date.now();if(!force&&now-lastRun<12000)return;lastRun=now;loading=true;
 try{
  const r=await fetch('/api/home',{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});if(!r.ok)return;
  const data=await r.json(),sections=Array.isArray(data?.sections)?data.sections:[];if(!sections.length)return;
  const used=visibleIds(main),titles=existingTitles(main),blocks=[];
  for(const s of sections){
   if(blocks.length>=4)break;
   const title=String(s?.title||'Jogos').trim(),key=title.toLowerCase();
   if(titles.has(key))continue;
   const games=(Array.isArray(s?.games)?s.games:[]).filter(g=>{const id=String(g?.id??'');return id&&!used.has(id)}).slice(0,10);
   if(games.length<4)continue;
   games.forEach(g=>used.add(String(g.id)));titles.add(key);
   blocks.push(`<section class="pfdh-section pfdh-extra-section"><div class="pfdh-section-head"><div><h2>${esc(title)}</h2>${s?.subtitle?`<p>${esc(s.subtitle)}</p>`:''}</div><a href="${esc(hrefFor(s))}">Ver todos →</a></div><div class="pfdh-games">${games.map(gameCard).join('')}</div></section>`);
  }
  if(!blocks.length)return;
  const wrap=document.createElement('div');wrap.dataset.pfdExtraSections='1';wrap.innerHTML=blocks.join('');main.appendChild(wrap);
 }catch(_){}finally{loading=false}
}
function watch(){
 const obs=new MutationObserver(()=>hydrate());obs.observe(document.body,{childList:true,subtree:true});
 setInterval(()=>{if(location.href!==lastHref){lastHref=location.href;hydrate(true)}else hydrate()},1000);
 addEventListener('popstate',()=>hydrate(true));addEventListener('resize',()=>hydrate(true),{passive:true});
}
function boot(){hydrate(true);watch()}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot,{once:true}):boot();
})();
