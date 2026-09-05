(()=>{'use strict';
const DESKTOP=()=>window.innerWidth>=1024;
const blocked=()=>/^\/admin(?:\/|$)/i.test(location.pathname)||/^\/games\/play(?:\/|$)/i.test(location.pathname)||/^\/sport(?:book)?\/play(?:\/|$)/i.test(location.pathname);
const isHome=()=>location.pathname==='/'||location.pathname==='';
const legacy=()=>document.getElementById('ondagamesv1');
let branding={},banners=[],homeData={sections:[]},providers=[],retroGames=[],account=null,heroTimer=null,lastPath='',observer=null;

const ICONS={
  home:'<path d="M3 11.5 12 4l9 7.5V21h-6v-6H9v6H3z"/>',
  game:'<path d="M7 9h10l3 3v5a3 3 0 0 1-3 3l-3-3h-4l-3 3a3 3 0 0 1-3-3v-5z"/><path d="M8 12v4M6 14h4"/>',
  live:'<circle cx="12" cy="12" r="2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4"/>',
  crown:'<path d="m3 7 4 4 5-7 5 7 4-4-2 11H5zM5 21h14"/>',
  gift:'<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13"/>',
  star:'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
  fire:'<path d="M12 22c4 0 7-3 7-7 0-3-2-6-5-9 0 4-2 5-3 6-1-3-3-5-5-6 1 3-1 5-1 8 0 5 3 8 7 8Z"/>',
  slots:'<rect x="4" y="7" width="16" height="12" rx="2"/><path d="M8 11h.01M12 11h.01M16 11h.01M8 15h8"/>',
  user:'<circle cx="12" cy="8" r="4"/><path d="M4 21c.7-5 3.2-7 8-7s7.3 2 8 7"/>',
  wallet:'<path d="M4 7h15a2 2 0 0 1 2 2v10H5a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h12v4"/><path d="M16 12h5v4h-5a2 2 0 0 1 0-4Z"/>',
  deposit:'<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
  withdraw:'<path d="M12 21V9M7 14l5-5 5 5M4 3h16"/>',
  search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
  bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
  sound:'<path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15 9a4 4 0 0 1 0 6M17.5 6.5a8 8 0 0 1 0 11"/>',
  shield:'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
  history:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
  users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8"/>',
  support:'<path d="M4 13a8 8 0 1 1 16 0v5a2 2 0 0 1-2 2h-3v-6h5M4 14h4v6H6a2 2 0 0 1-2-2z"/>',
  percent:'<path d="m5 19 14-14M7 7h.01M17 17h.01"/>',
  target:'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/>',
  trophy:'<path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H4v2a4 4 0 0 0 4 4M16 6h4v2a4 4 0 0 1-4 4M12 13v5M8 21h8"/>',
  arrow:'<path d="m9 18 6-6-6-6"/>',
  heart:'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.9-8.6a5.5 5.5 0 0 0-.1-7.8Z"/>'
};
function ico(name,cls=''){return `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]||ICONS.star}</svg>`}
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function asset(v){if(!v)return '';v=String(v);if(/^(?:https?:|data:|blob:)/i.test(v))return v;if(v.startsWith('/'))return v;v=v.replace(/^\.?\//,'').replace(/^public\//,'');if(v.startsWith('storage/'))return '/'+v;if(v.startsWith('uploads/'))return '/storage/'+v;return '/storage/'+v}
function slugify(v){return String(v||'jogo').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')||'jogo'}
function money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
function token(){return localStorage.getItem('token')||''}
function session(){return localStorage.getItem('session_token')||''}
function headers(){const h={Accept:'application/json','X-Requested-With':'XMLHttpRequest'};if(token())h.Authorization='Bearer '+token();if(session())h['X-Session-Token']=session();return h}
async function json(url,auth=false){const r=await fetch(url,{credentials:'same-origin',headers:auth?headers():{Accept:'application/json'}});if(!r.ok)throw new Error(String(r.status));return r.json()}
function walletTotal(){const w=account?.wallet;if(!w)return null;return Number.isFinite(Number(w.total_balance))?Number(w.total_balance):Number(w.balance||0)+Number(w.balance_withdrawal||0)+Number(w.balance_bonus||0)}
function userName(){return account?.user?.name||'Minha conta'}
function initials(){return userName().split(/\s+/).filter(Boolean).slice(0,2).map(x=>x[0]).join('').toUpperCase()||'PF'}

async function loadData(){
  const tasks=[
    json('/branding/data').catch(()=>({})),
    json('/api/settings/banners/').catch(()=>({desktop:[],banners:[]})),
    json('/api/home').catch(()=>({sections:[]})),
    json('/api/home/providers').catch(()=>({providers:[]})),
    json('/api/retro/games?home=1').catch(()=>({games:[]})),
  ];
  if(token())tasks.push(json('/api/profile/account',true).catch(()=>null));else tasks.push(Promise.resolve(null));
  const [b,bn,h,p,r,a]=await Promise.all(tasks);
  branding=b||{};
  banners=Array.isArray(bn?.desktop)?bn.desktop:(Array.isArray(bn?.banners)?bn.banners.filter(x=>x.show_desktop!==false):[]);
  homeData=h||{sections:[]};
  providers=p?.providers||[];
  retroGames=r?.games||[];
  account=a;
}

function sidebar(){
  const logo=asset(branding.desktop_logo||branding.mobile_logo)||'/pixfacil-v15/logo.svg';
  const items=[
    ['home','/','Início'],['game','/casino/provider/all/category/all','Jogos'],['live','/casino/provider/all/category/live','Ao Vivo'],
    ['crown','/retro','Originais'],['fire','/casino/provider/all/category/all?sort=popular','Crash'],['game','/retro','Arcade'],
    ['gift','/bonus','Bônus'],['star','/vip','VIP'],['percent','/promocoes','Promoções']
  ];
  const lower=[['user','/profile/account','Minha Conta'],['history','/profile/transactions','Transações'],['users','/profile/affiliate','Afiliados'],['support','/support-center','Suporte']];
  const item=([ic,href,label])=>`<a href="${href}" class="pfx-side-item${location.pathname===href||(href!=='/'&&location.pathname.startsWith(href.split('?')[0]))?' active':''}">${ico(ic)}<span>${esc(label)}</span></a>`;
  return `<aside id="pf-desktop-sidebar"><a href="/" class="pfx-side-logo"><img src="${esc(logo)}" alt="${esc(branding.software_name||'PixFácil')}"></a><nav class="pfx-side-main">${items.map(item).join('')}</nav><div class="pfx-side-sep"></div><nav>${lower.map(item).join('')}</nav><div class="pfx-side-bottom"><a href="/profile/responsible-gaming" class="pfx-responsible"><b>18+</b><span><strong>Jogo Responsável</strong><small>Diversão com consciência</small></span></a></div></aside>`;
}

function topbar(){
  const total=walletTotal(), logged=!!account?.user;
  return `<header id="pf-desktop-chrome"><div class="pfx-top-search">${ico('search')}<input id="pfx-global-search" placeholder="Buscar jogos, provedores, categorias..." autocomplete="off"><kbd>Ctrl K</kbd></div><div class="pfx-top-actions"><button type="button" class="pfx-round" aria-label="Som">${ico('sound')}</button><button type="button" class="pfx-round" aria-label="Notificações">${ico('bell')}<i></i></button>${logged?`<a href="/profile/account" class="pfx-balance"><span>Saldo total</span><strong>${money(total)}</strong></a>`:`<a href="/login" class="pfx-login">Entrar</a>`}<a href="/profile/deposit" class="pfx-deposit">${ico('deposit')}<span>Depositar</span></a><a href="/profile/withdraw" class="pfx-withdraw">${ico('withdraw')}<span>Sacar</span></a><a href="/profile/account" class="pfx-user"><b>${esc(initials())}</b><span>${esc(logged?userName():'Perfil')}<small>${logged?'Minha conta':'Entrar ou cadastrar'}</small></span></a></div></header>`;
}

function hero(){
  const list=banners.filter(x=>x&&x.image&&x.is_active!==false&&x.show_desktop!==false);
  if(!list.length)return `<section class="pfx-hero pfx-hero-empty"><div><small>${esc(branding.software_name||'PixFácil')}</small><h1>Seu banner principal<br>vem do <b>Admin</b>.</h1><p>Cadastre em Tema e Aparência → Banners da Plataforma.</p></div></section>`;
  return `<section class="pfx-hero">${list.map((b,i)=>`<article class="pfx-hero-slide${i===0?' active':''}" data-pfx-slide="${i}"><img src="${esc(asset(b.image))}" alt="${esc(b.description||'Banner')}">${b.link?`<a href="${esc(b.link)}" aria-label="${esc(b.description||'Abrir banner')}"></a>`:''}</article>`).join('')}<div class="pfx-hero-dots">${list.map((_,i)=>`<button type="button" class="${i===0?'active':''}" data-pfx-dot="${i}" aria-label="Banner ${i+1}"></button>`).join('')}</div></section>`;
}

function quickActions(){
  const total=walletTotal();
  return `<div class="pfx-quick-row">
    <a href="${account?'/profile/account':'/login'}" class="pfx-quick balance">${ico('wallet')}<span><small>Saldo total</small><strong>${account?money(total):'Entrar'}</strong></span></a>
    <a href="/profile/deposit" class="pfx-quick">${ico('deposit')}<span><strong>Depositar</strong><small>Via PIX</small></span></a>
    <a href="/profile/withdraw" class="pfx-quick">${ico('withdraw')}<span><strong>Sacar</strong><small>Receba no seu PIX</small></span></a>
    <a href="/bonus" class="pfx-quick">${ico('gift')}<span><strong>Bônus</strong><small>Veja suas ofertas</small></span></a>
    <a href="/vip" class="pfx-quick">${ico('crown')}<span><strong>VIP</strong><small>Seu progresso</small></span></a>
    <a href="/missions" class="pfx-quick trophy">${ico('trophy')}<span><strong>Missões</strong><small>Complete e ganhe</small></span></a>
  </div>`;
}

function chips(){
  return `<nav class="pfx-chips">
    <a class="active" href="/casino/provider/all/category/all?sort=popular">${ico('fire')}Jogos quentes</a>
    <a href="/casino/provider/all/category/slots">${ico('slots')}Slots</a>
    <a href="/casino/provider/all/category/live">${ico('live')}Ao vivo</a>
    <a href="/casino/provider/all/category/all?sort=new">${ico('star')}Novos</a>
    <a href="/retro">${ico('game')}Arcade</a>
    <a href="/casino/provider/all/category/all">${ico('game')}Cassino</a>
    <a href="/retro">${ico('crown')}Originais</a>
  </nav>`;
}

function providerPriority(p){const s=`${p?.name||''} ${p?.code||''}`.toLowerCase();const order=[/pg soft|pgsoft|pocket games|\bpg\b/,/pragmatic/,/evolution/,/jili/,/spribe/,/hacksaw/,/play.?n go/];const i=order.findIndex(rx=>rx.test(s));return i<0?99:i}
function providerCard(p){
  const img=asset(p.cover||p.pixfacil_home_cover||p.home_cover),name=p.name||p.code||'Operadora';
  return `<a href="/casino/provider/${encodeURIComponent(p.id)}/category/all" class="pfx-provider">${img?`<img src="${esc(img)}" alt="${esc(name)}" loading="lazy">`:`<b>${esc(name)}</b>`}<span>${esc(name)}</span></a>`;
}
function providersRow(){
  const list=[...providers].sort((a,b)=>providerPriority(a)-providerPriority(b)||String(a.name||'').localeCompare(String(b.name||''),'pt-BR')).slice(0,10);
  return sectionHead('Operadoras','/casino/provider/all/category/all',`<div class="pfx-providers">${list.map(providerCard).join('')}</div>`);
}

function gameCard(g){
  const cover=asset(g.cover),name=g.game_name||g.game_code||'Jogo',provider=g.provider||'';
  const href=`/games/play/${encodeURIComponent(g.id)}/${encodeURIComponent(slugify(name))}`;
  return `<a class="pfx-game" href="${href}"><div class="pfx-game-art">${cover?`<img src="${esc(cover)}" alt="${esc(name)}" loading="lazy">`:''}<button type="button" tabindex="-1" aria-hidden="true">${ico('heart')}</button><i class="pfx-play">${ico('game')}</i></div><strong>${esc(name)}</strong><span>${esc(provider)}</span></a>`;
}
function retroCard(g){
  const cover=g.cover_url||asset(g.cover),name=g.name||g.slug||'Original';
  return `<a class="pfx-game pfx-original" href="/retro/game/${encodeURIComponent(g.slug)}"><div class="pfx-game-art">${cover?`<img src="${esc(cover)}" alt="${esc(name)}" loading="lazy">`:''}<em>ORIGINAL</em><i class="pfx-play">${ico('game')}</i></div><strong>${esc(name)}</strong><span>PixFácil Original</span></a>`;
}
function sectionHead(title,href,body,sub=''){
  return `<section class="pfx-section"><div class="pfx-section-head"><div><h2>${esc(title)}</h2>${sub?`<p>${esc(sub)}</p>`:''}</div>${href?`<a href="${href}">Ver todos →</a>`:''}</div>${body}</section>`;
}
function chooseGames(type,limit=6){
  const sections=homeData.sections||[];
  const s=sections.find(x=>x.type===type)||sections.find(x=>Array.isArray(x.games)&&x.games.length);
  return (s?.games||[]).slice(0,limit);
}
function hotSection(){
  let games=chooseGames('popular',6);if(!games.length)games=chooseGames('featured',6);
  return sectionHead('🔥 Jogos Quentes','/casino/provider/all/category/all?sort=popular',`<div class="pfx-games">${games.map(gameCard).join('')}</div>`,'Os favoritos da galera, jogue agora!');
}
function originalSection(){
  return sectionHead('🎮 PixFácil Originals','/retro',`<div class="pfx-games">${retroGames.slice(0,6).map(retroCard).join('')}</div>`,'Jogos exclusivos, feitos para você!');
}
function newSection(){
  const sec=(homeData.sections||[]).find(x=>x.type==='new');const games=(sec?.games||[]).slice(0,6);
  return games.length?sectionHead(sec.title||'Novos Jogos','/casino/provider/all/category/all?sort=new',`<div class="pfx-games">${games.map(gameCard).join('')}</div>`,sec.subtitle||'Novidades da plataforma'):'';
}

function promoRail(){
  const extras=banners.filter(x=>x&&x.image&&x.is_active!==false&&x.show_desktop!==false).slice(1,4);
  const adminCards=extras.map(b=>`<a class="pfx-side-banner" href="${esc(b.link||'#')}"${b.link?'':' aria-disabled="true"'}><img src="${esc(asset(b.image))}" alt="${esc(b.description||'Promoção')}" loading="lazy"></a>`).join('');
  return `<aside class="pfx-right-rail"><div id="pfx-live-card" class="pfx-live-card" hidden></div>${adminCards}<a href="/vip" class="pfx-rail-cta gold">${ico('crown')}<span><small>TORNE-SE VIP</small><strong>Mais benefícios<br>Mais recompensas</strong><b>Conhecer planos →</b></span></a><a href="/profile/deposit" class="pfx-rail-cta">${ico('shield')}<span><small>PIX RÁPIDO</small><strong>Seguro e sem burocracia</strong><b>Depositar →</b></span></a><div class="pfx-rail-list"><div class="pfx-rail-title">${ico('gift')}<strong>Promoções</strong><a href="/promocoes">Ver todas →</a></div><a href="/bonus">${ico('gift')}<span><strong>Bônus</strong><small>Confira suas ofertas</small></span>${ico('arrow')}</a><a href="/vip">${ico('crown')}<span><strong>VIP</strong><small>Veja seus benefícios</small></span>${ico('arrow')}</a><a href="/missions">${ico('target')}<span><strong>Missões</strong><small>Complete e ganhe</small></span>${ico('arrow')}</a></div></aside>`;
}

function homeMarkup(){
  return `<div id="pf-desktop-experience"><main class="pfx-layout"><div class="pfx-center">${hero()}${quickActions()}${chips()}${providersRow()}${hotSection()}${originalSection()}${newSection()}</div>${promoRail()}</main></div>`;
}

function renderHome(){
  document.body.classList.add('pf-desktop-home');
  let mount=document.getElementById('pf-desktop-experience');
  if(!mount){mount=document.createElement('div');mount.id='pf-desktop-experience';document.body.appendChild(mount)}
  mount.outerHTML=homeMarkup();
  setupHero();
  setupSearch();
  setTimeout(extractLegacyLive,500);
}
function clearHome(){document.body.classList.remove('pf-desktop-home');document.getElementById('pf-desktop-experience')?.remove();clearInterval(heroTimer);heroTimer=null}

function setupHero(){
  clearInterval(heroTimer);heroTimer=null;
  const mount=document.getElementById('pf-desktop-experience');if(!mount)return;
  const slides=[...mount.querySelectorAll('[data-pfx-slide]')],dots=[...mount.querySelectorAll('[data-pfx-dot]')];if(slides.length<2)return;
  let idx=0;const show=n=>{idx=(n+slides.length)%slides.length;slides.forEach((s,i)=>s.classList.toggle('active',i===idx));dots.forEach((d,i)=>d.classList.toggle('active',i===idx))};
  const restart=()=>{clearInterval(heroTimer);heroTimer=setInterval(()=>show(idx+1),6500)};
  dots.forEach((d,i)=>d.addEventListener('click',()=>{show(i);restart()}));restart();
}
function setupSearch(){
  const input=document.getElementById('pfx-global-search');if(!input)return;
  const go=()=>{const q=input.value.trim();if(q)location.href='/pesquisar?search='+encodeURIComponent(q)};
  input.addEventListener('keydown',e=>{if(e.key==='Enter')go()});
}
function extractLegacyLive(){
  const root=legacy(),card=document.getElementById('pfx-live-card');if(!root||!card)return;
  const els=[...root.querySelectorAll('h1,h2,h3,h4,div,span')],hit=els.find(e=>/ganhos ao vivo/i.test((e.textContent||'').trim())&&(e.textContent||'').trim().length<80);if(!hit)return;
  let box=hit;for(let i=0;i<7&&box;i++,box=box.parentElement){const txt=box.innerText||'';if((txt.match(/R\$\s*[\d.,]+/g)||[]).length>=2&&box.querySelectorAll('img').length>=2&&txt.length<5000)break}if(!box)return;
  const rows=[],seen=new Set();for(const el of box.querySelectorAll('div,li,article')){const img=el.querySelector('img'),txt=(el.innerText||'').replace(/\s+/g,' ').trim(),vals=txt.match(/R\$\s*[\d.,]+/g)||[];if(!img||!vals.length||txt.length>300||txt.length<10)continue;const key=img.src+'|'+vals.at(-1);if(seen.has(key))continue;seen.add(key);rows.push({img:img.src,name:txt.split(/R\$/)[0].trim().split(/\s+/).slice(0,2).join(' ')||'Jogador',amount:vals.at(-1)});if(rows.length>=4)break}
  if(rows.length<2)return;
  card.hidden=false;card.innerHTML=`<div class="pfx-rail-title"><i></i><strong>Ganhos ao vivo</strong><a href="/casino/provider/all/category/all">Ver todos →</a></div>${rows.map(r=>`<div class="pfx-live-row"><img src="${esc(r.img)}" alt=""><span><strong>${esc(r.name)}</strong><small>ganhou <b>${esc(r.amount)}</b></small></span></div>`).join('')}`;
}

function mountFrame(){
  if(!DESKTOP()||blocked()){unmount();return}
  document.body.classList.add('pf-desktop-owned');
  let side=document.getElementById('pf-desktop-sidebar');if(!side){document.body.insertAdjacentHTML('afterbegin',sidebar())}else side.outerHTML=sidebar();
  let top=document.getElementById('pf-desktop-chrome');if(!top){document.body.insertAdjacentHTML('afterbegin',topbar())}else top.outerHTML=topbar();
  setupSearch();
  if(isHome())renderHome();else clearHome();
}
function unmount(){
  clearHome();document.body.classList.remove('pf-desktop-owned');
  document.getElementById('pf-desktop-sidebar')?.remove();document.getElementById('pf-desktop-chrome')?.remove();
}
function routeTick(){
  if(!DESKTOP()||blocked()){unmount();return}
  if(location.pathname!==lastPath){lastPath=location.pathname;mountFrame()}
}
function watch(){
  setInterval(routeTick,500);addEventListener('popstate',routeTick);addEventListener('resize',routeTick);
  document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'&&DESKTOP()&&!blocked()){e.preventDefault();document.getElementById('pfx-global-search')?.focus()}});
  const root=legacy();if(root){observer=new MutationObserver(()=>{if(isHome()&&DESKTOP()){extractLegacyLive()}});observer.observe(root,{childList:true,subtree:true})}
}
async function boot(){
  if(!DESKTOP()||blocked())return;
  await loadData();lastPath=location.pathname;mountFrame();watch();
}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot):boot();
})();