(()=>{'use strict';
const isHome=()=>location.pathname.replace(/\/+$/,'')===''||location.pathname==='/';
const isDesktop=()=>window.innerWidth>=768;
const currentPath=()=>location.pathname.replace(/\/+$/,'')||'/';
let lastHref=location.href,loading=false,lastRun=0;
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function asset(v){if(!v)return '';v=String(v).trim();if(/^(?:https?:|data:|blob:)/i.test(v))return v;if(v.startsWith('/'))return v;v=v.replace(/^\.\//,'').replace(/^public\//,'');if(v.startsWith('storage/'))return '/'+v;if(v.startsWith('uploads/'))return '/storage/'+v;return '/storage/'+v}
function slug(v){return String(v||'jogo').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')||'jogo'}
function hrefFor(section){if(section?.type==='category'&&section.slug)return '/casino/provider/all/category/'+encodeURIComponent(section.slug);if(section?.type==='new')return '/casino/provider/all/category/all?sort=new';if(section?.type==='popular')return '/casino/provider/all/category/all?sort=popular';return '/casino/provider/all/category/all'}
function gameCard(g){const name=g?.game_name||g?.name||g?.game_code||'Jogo',img=asset(g?.cover);return `<a class="pfdh-game" href="/games/play/${encodeURIComponent(g.id)}/${encodeURIComponent(slug(name))}" data-pf-extra-game="${esc(g.id)}"><span class="pfdh-game-art">${img?`<img src="${esc(img)}" loading="lazy" alt="">`:''}</span><strong>${esc(name)}</strong><small>${esc(g?.provider||'')}</small></a>`}
function visibleIds(root){const set=new Set();for(const a of root.querySelectorAll('a[href*="/games/play/"]')){const m=(a.getAttribute('href')||'').match(/\/games\/play\/([^/]+)/i);if(m)set.add(String(m[1]))}return set}
function existingTitles(root){return new Set([...root.querySelectorAll('.pfdh-section-head h2')].map(x=>(x.textContent||'').trim().toLowerCase()))}
function sportsIcon(){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="m9.4 9 2.6-1.9L14.6 9l-1 3.1h-3.2zM5.1 10.1l3 .3 1 3-2.3 2M18.9 10.1l-3 .3-1 3 2.3 2M9 18.5l1.4-2.7h3.2l1.4 2.7"/></svg>'}
function syncSportsbookDesktop(){
 const nav=document.querySelector('#pfd-sidebar .pfd-nav');
 if(!nav)return;
 let item=nav.querySelector('[data-pf-sportsbook-nav="desktop"]');
 if(!item){
  item=document.createElement('a');
  item.href='/sporting';
  item.dataset.pfSportsbookNav='desktop';
  item.className='pfd-nav-item';
  item.innerHTML=`${sportsIcon()}<span>Sportsbook</span>`;
  const live=[...nav.querySelectorAll('.pfd-nav-item')].find(a=>/ao vivo/i.test((a.textContent||'').trim()));
  if(live)live.after(item);else nav.prepend(item);
 }
 item.classList.toggle('active',currentPath().startsWith('/sporting'));
}
function syncSportsbookMobile(){
 const nav=document.querySelector('.pf8-bottom.pf11-bottom');
 if(!nav)return;
 const slot=nav.querySelector('[data-pf11-slot="4"]');
 if(!slot)return;
 slot.href='/sporting';
 slot.dataset.pf8Nav='';
 slot.dataset.pfSportsbookNav='mobile';
 slot.setAttribute('aria-label','Sportsbook');
 slot.className=(currentPath().startsWith('/sporting')?'active ':'')+'pf11-nav-sportsbook';
 if(slot.dataset.pfSportsbookReady!=='1'){
  slot.dataset.pfSportsbookReady='1';
  slot.innerHTML=`${sportsIcon()}<span>Sportsbook</span>`;
 }
}
function syncSportsbookNav(){
 if(isDesktop())syncSportsbookDesktop();else syncSportsbookMobile();
}
async function hydrate(force=false){
 syncSportsbookNav();
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
 const obs=new MutationObserver(()=>{syncSportsbookNav();hydrate()});obs.observe(document.body,{childList:true,subtree:true});
 setInterval(()=>{syncSportsbookNav();if(location.href!==lastHref){lastHref=location.href;hydrate(true)}else hydrate()},1000);
 addEventListener('popstate',()=>{syncSportsbookNav();hydrate(true)});addEventListener('resize',()=>{syncSportsbookNav();hydrate(true)},{passive:true});
}
function boot(){syncSportsbookNav();hydrate(true);watch()}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot,{once:true}):boot();
})();
