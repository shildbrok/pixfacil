(()=>{'use strict';
const CFG=window.PIXFACIL_MOBILE_CONFIG||{};
window.PIXFACIL_V14_ACTIVE='14.0.0';
window.addEventListener('error',function(ev){
  try{
    const root=document.getElementById('pixfacil-v14-app');
    if(!root||!document.body.classList.contains('pf14-owned-server'))return;
    if(root.querySelector('.pf81-fatal'))return;
    const box=document.createElement('div');
    box.className='pf81-fatal';
    box.style.cssText='margin:120px 18px 0;padding:18px;border:1px solid #2f5137;border-radius:16px;background:#080c09;color:#fff;font:14px Inter,Arial,sans-serif';
    box.innerHTML='<b style="color:#39f25c">PixFácil encontrou um erro nesta tela.</b><br><span style="color:#a7afa9">Atualize a página. Se continuar, envie o erro do console.</span>';
    root.appendChild(box);
  }catch(_){}
});
const app=()=>document.getElementById('pixfacil-v14-app');

document.addEventListener('error',function(e){
  const img=e.target;
  if(!(img instanceof HTMLImageElement))return;
  const fallback=img.dataset.pf12Fallback;
  if(fallback){
    const absolute=new URL(fallback,location.origin).href;
    if(img.src!==absolute){
      img.dataset.pf12Fallback='';
      img.src=fallback;
      return;
    }
  }
  img.classList.add('pf12-img-failed');
},true);

const oldRoot=()=>document.getElementById('ondagamesv1');
const MOBILE=()=>window.innerWidth<=767;
let renderSeq=0, pollTimer=null, casinoState=null, urlWatch=null, lastHref=location.href;

const ICONS={
  home:'<path d="M3 11.5 12 4l9 7.5V21h-6v-6H9v6H3z"/>',
  game:'<path d="M7 9h10l3 3v5a3 3 0 0 1-3 3l-3-3h-4l-3 3a3 3 0 0 1-3-3v-5z"/><path d="M8 12v4M6 14h4M16.5 13.5h.01M18.5 15.5h.01"/>',
  gift:'<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13M12 7c-1-4-5-4-6-2-1.5 3 3 4 6 2ZM12 7c1-4 5-4 6-2 1.5 3-3 4-6 2Z"/>',
  user:'<circle cx="12" cy="8" r="4"/><path d="M4 21c.7-5 3.2-7 8-7s7.3 2 8 7"/>',
  bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
  eye:'<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
  eyeoff:'<path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8M6.6 6.6C3.5 8.4 2 12 2 12s3.5 6 10 6a10.5 10.5 0 0 0 4.1-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
  deposit:'<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
  withdraw:'<path d="M12 21V9M7 14l5-5 5 5M4 3h16"/>',
  search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
  fire:'<path d="M12 22c4 0 7-3 7-7 0-3-2-6-5-9 0 4-2 5-3 6-1-3-3-5-5-6 1 3-1 5-1 8 0 5 3 8 7 8Z"/>',
  slots:'<rect x="4" y="7" width="16" height="12" rx="2"/><path d="M8 11h.01M12 11h.01M16 11h.01M8 15h8M8 4h8"/>',
  live:'<circle cx="12" cy="12" r="2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.5 4.5a10.5 10.5 0 0 0 0 15M19.5 4.5a10.5 10.5 0 0 1 0 15"/>',
  star:'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
  heart:'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
  back:'<path d="m15 18-6-6 6-6"/>',
  copy:'<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
  wallet:'<path d="M4 7h15a2 2 0 0 1 2 2v10H5a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h12v4M16 12h5v4h-5a2 2 0 0 1 0-4Z"/>',
  history:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
  users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
  shield:'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
  support:'<path d="M4 13a8 8 0 1 1 16 0v5a2 2 0 0 1-2 2h-3v-6h5M4 14h4v6H6a2 2 0 0 1-2-2z"/>',
  gear:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
  logout:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
  crown:'<path d="m3 7 4 4 5-7 5 7 4-4-2 11H5zM5 21h14"/>',
  target:'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="m15 9 6-6M16 3h5v5"/>',
  trophy:'<path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H4v2a4 4 0 0 0 4 4M16 6h4v2a4 4 0 0 1-4 4M12 13v5M8 21h8M9 18h6"/>',
  percent:'<path d="m5 19 14-14M7 7h.01M17 17h.01"/>',
  check:'<path d="m5 12 4 4L19 6"/>',
  plus:'<path d="M12 5v14M5 12h14"/>'
};
function ico(name,cls=''){return `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]||ICONS.star}</svg>`}
const pixmark=()=>`<svg class="pf8-brand-mark" viewBox="0 0 32 32" aria-hidden="true"><g fill="#39f25c"><rect x="3" y="3" width="11" height="11" rx="3" transform="rotate(45 8.5 8.5)"/><rect x="18" y="3" width="11" height="11" rx="3" transform="rotate(45 23.5 8.5)"/><rect x="3" y="18" width="11" height="11" rx="3" transform="rotate(45 8.5 23.5)"/><rect x="18" y="18" width="11" height="11" rx="3" transform="rotate(45 23.5 23.5)"/></g></svg>`;
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function attr(v){return esc(v).replace(/`/g,'&#096;')}
function strip(v){const d=document.createElement('div');d.innerHTML=String(v??'');return (d.textContent||'').replace(/\s+/g,' ').trim()}
function money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
function dateBR(v){if(!v)return '';try{return new Date(v).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}catch{return ''}}
function slugify(v){return String(v||'jogo').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')||'jogo'}
function asset(v){if(!v)return '';v=String(v);if(/^(?:https?:|data:|blob:)/i.test(v))return v;if(v.startsWith('/'))return v;v=v.replace(/^\.\//,'').replace(/^public\//,'');if(v.startsWith('storage/'))return '/'+v;if(v.startsWith('uploads/'))return '/storage/'+v;return '/storage/'+v}
function path(){return location.pathname.replace(/\/+$/,'')||'/'}
function token(){return localStorage.getItem('token')||''}
function session(){return localStorage.getItem('session_token')||''}
function authed(){return !!token()}
function clearAuth(){['token','session_token','user'].forEach(k=>localStorage.removeItem(k))}
function headers(json=true){const h={Accept:'application/json','X-Requested-With':'XMLHttpRequest'};if(json)h['Content-Type']='application/json';if(token())h.Authorization='Bearer '+token();if(session())h['X-Session-Token']=session();return h}
async function api(url,opt={}){const o={credentials:'same-origin',...opt};o.headers={...headers(!(opt.body instanceof FormData)),...(opt.headers||{})};if(o.body&&typeof o.body==='object'&&!(o.body instanceof FormData))o.body=JSON.stringify(o.body);const r=await fetch(url,o);let data=null;const ct=r.headers.get('content-type')||'';try{data=ct.includes('json')?await r.json():await r.text()}catch{}if(r.status===401){clearAuth();throw Object.assign(new Error('Sua sessão expirou. Faça login novamente.'),{status:401,data})}if(!r.ok){let msg='Não foi possível concluir a solicitação.';if(data&&typeof data==='object'){msg=data.error||data.message||Object.values(data).flat().filter(Boolean)[0]||msg}else if(typeof data==='string'&&data)msg=data;throw Object.assign(new Error(String(msg)),{status:r.status,data})}return data}
function saveAuth(d){if(d?.access_token)localStorage.setItem('token',d.access_token);if(d?.session_token)localStorage.setItem('session_token',d.session_token);if(d?.user)localStorage.setItem('user',JSON.stringify(d.user))}
function toast(msg,err=false){document.querySelector('.pf8-toast')?.remove();const x=document.createElement('div');x.className='pf8-toast'+(err?' err':'');x.textContent=String(msg||'');document.body.appendChild(x);setTimeout(()=>x.remove(),3800)}
function brand(){
  const src=CFG.pixfacilLogo||'/pixfacil-v14/logo.svg';
  return `<img src="${attr(src)}" data-pf12-fallback="/pixfacil-v14/logo.svg" alt="PixFácil">`
}
function loader(){return `<div class="pf9-inline-skeleton"><span></span><span></span><span></span></div>`}
function topbar(){return `<div class="pf8-top"><a href="/" data-pf8-nav class="pf8-brand">${brand()}</a><div class="pf8-tools"><a href="/bonus" data-pf8-nav class="pf8-iconbtn pf10-iconbtn" aria-label="Bônus">${ico('bell')}<i class="pf8-dot"></i></a><a href="/profile/account" data-pf8-nav class="pf8-iconbtn pf10-iconbtn" aria-label="Perfil">${ico('user')}</a></div></div>`}
function bottom(active='home'){
  const item=(key,href,icon,label,slot)=>`<a href="${href}" data-pf8-nav data-pf11-slot="${slot}" class="${active===key?'active ':''}pf11-nav-${key}" aria-label="${label}">${ico(icon)}<span>${label}</span></a>`;
  return `<nav class="pf8-bottom pf11-bottom" data-pf11-nav>
    ${item('home','/','home','Início','1')}
    ${item('games','/casino/provider/all/category/all','game','Jogos','2')}
    <a href="/profile/deposit" data-pf8-nav data-pf11-center="1" data-pf11-slot="3" class="center pf11-nav-center" aria-label="Depositar"><span class="pf8-center pf11-center">${pixmark()}</span></a>
    ${item('bonus','/bonus','gift','Bônus','4')}
    ${item('profile','/profile/account','user','Perfil','5')}
  </nav>`
}
function shell(content,{active='home',auth=false,bottomNav=true}={}){
  const ambient=auth?'':`<div class="pf10-ambient" aria-hidden="true"><i></i><i></i><i></i></div>`;
  return `<main class="pf8-shell${auth?' pf8-auth':''}">${ambient}<div class="pf8-wrap">${content}</div>${bottomNav?bottom(active):''}</main>`
}
function moneyRow(account){const bal=account?.wallet?.balance??0;return `<div class="pf8-walletrow pf11-walletrow"><div class="pf8-balance pf11-balance"><div><small>Saldo disponível</small><strong class="pf8-balance-text">${money(bal)}</strong></div><button class="pf8-eye" type="button" data-action="toggle-balance">${ico('eye')}</button></div><a href="/profile/deposit" data-pf8-nav class="pf8-action green pf10-cta pf11-action">${ico('deposit')}<span>Depositar</span></a><a href="/profile/withdraw" data-pf8-nav class="pf8-action dark pf10-darkcta pf11-action">${ico('withdraw')}<span>Sacar</span></a></div>`}
function sec(title,link='',body=''){return `<section class="pf8-section pf10-reveal"><div class="pf8-sec-head"><h2>${esc(title)}</h2>${link?`<a href="${attr(link)}" data-pf8-nav>VER TODOS →</a>`:''}</div>${body}</section>`}
function gameCard(g,variant=''){
  const cover=asset(g.cover);
  const href=`/games/play/${encodeURIComponent(g.id)}/${encodeURIComponent(slugify(g.game_name||g.game_code))}`;
  const rtp=Number(g.rtp);
  const hot=variant==='hot';
  const fresh=variant==='new';
  const cardClass=`pf8-game${hot?' pf12-hot-card':''}${fresh?' pf12-new-card':''}`;
  const special=hot
    ? `<span class="pf12-firefx" aria-hidden="true"><i></i><i></i><i></i><i></i></span>${Number.isFinite(rtp)&&rtp>0?`<span class="pf12-rtp">RTP ${Math.round(rtp)}%</span>`:''}`
    : fresh
      ? `<span class="pf12-new-badge">NEW</span>`
      : '';
  return `<a class="${cardClass}" href="${href}" data-pf8-game>
    <div class="pf8-game-art pf10-game-art">
      ${cover?`<img loading="lazy" src="${attr(cover)}" alt="${attr(g.game_name||'Jogo')}">`:''}
      ${special}
      <span class="pf8-heart">${ico('heart')}</span>
    </div>
    <span class="pf8-game-name">${esc(g.game_name||g.game_code||'Jogo')}</span>
    <span class="pf8-game-provider">${esc(g.provider||'')}</span>
  </a>`
}
function providerCard(p){
  const custom=asset(p.pixfacil_home_cover||p.home_cover);
  const fallback=asset(p.cover);
  const img=custom||fallback;
  const bg=fallback||custom;
  const style=bg?` style="--pf11-provider-bg:url(&quot;${attr(bg)}&quot;)"`:'';
  const imgClass=custom?'pf11-provider-img is-art':'pf11-provider-img is-logo';
  return `<a href="/casino/provider/${encodeURIComponent(p.id)}/category/all" data-pf8-nav class="pf8-provider pf11-provider"${style} title="${attr(p.name)}"><span class="pf11-provider-shine"></span>${img?`<img loading="lazy" class="${imgClass}" src="${attr(img)}"${custom&&fallback?` data-pf12-fallback="${attr(fallback)}"`:''} alt="${attr(p.name)}">`:`<strong>${esc(p.name)}</strong>`}</a>`
}
function pageHead(title,sub='',fallback='/profile/account'){
  return `<div class="pf8-pagehead"><button type="button" class="pf8-back" data-action="back" data-back-fallback="${attr(fallback)}">${ico('back')}</button><div class="pf8-title"><h1>${esc(title)}</h1>${sub?`<p>${esc(sub)}</p>`:''}</div></div>`
}
function empty(t='Nada para mostrar por enquanto.'){return `<div class="pf8-empty">${esc(t)}</div>`}
function errBlock(e){return `<div class="pf8-empty">${esc(e?.message||'Não foi possível carregar esta tela.')}<br><button class="pf8-mission-btn ready" data-action="reload" style="margin-top:12px">Tentar novamente</button></div>`}

const PF13_ROUTE_ALIASES={
  '/profile/affiliates':'/profile/affiliate',
  '/support':'/support-center',
  '/profile/settings':'/profile/responsible-gaming'
};
function gamePath(p=path()){return /^\/games\/play(?:\/|$)/i.test(p)||/^\/sport(?:book)?\/play(?:\/|$)/i.test(p)}
function owned(p=path()){
  if(!MOBILE()||/^\/admin(?:\/|$)/i.test(p)||gamePath(p))return false;
  return p==='/'||
    /^\/(?:login|register|forget-password|forgot-password|reset-password)(?:\/|$)/i.test(p)||
    /^\/(?:casino|pesquisar|search)(?:\/|$)/i.test(p)||
    /^\/profile\/(?:account|deposit|withdraw|transactions|bets|affiliate|verification|responsible-gaming)(?:\/|$)/i.test(p)||
    /^\/support-center(?:\/|$)/i.test(p)||
    /^\/retro(?:\/|$)/i.test(p)||
    /^\/(?:bonus|vip|promotion|promotions|promocoes)(?:\/|$)/i.test(p)
}
function setOwned(on){
  const serverOwned=document.body.classList.contains('pf14-owned-server')||window.PIXFACIL_V14_SERVER_OWNED===true;
  document.body.classList.toggle('pf8-owned',on||serverOwned);
  if(!on&&!serverOwned&&app())app().innerHTML='';
}
function currentInternalUrl(){return location.pathname+location.search+location.hash}
function pf13Stack(){
  try{
    const x=JSON.parse(sessionStorage.getItem('pf13_nav_stack')||'[]');
    return Array.isArray(x)?x.slice(-20):[];
  }catch{return []}
}
function pf13SaveStack(s){try{sessionStorage.setItem('pf13_nav_stack',JSON.stringify(s.slice(-20)))}catch{}}
function normalizeNav(raw){
  if(raw===undefined||raw===null)return null;
  const value=String(raw).trim();
  if(!value||/^(?:undefined|null|false|#)$/i.test(value)||/^javascript:/i.test(value))return null;
  let u;
  try{u=new URL(value,location.origin)}catch{return null}
  if(!/^https?:$/i.test(u.protocol))return null;
  if(u.origin===location.origin){
    if(/^\/(?:undefined|null)(?:\/|$)/i.test(u.pathname))return null;
    const clean=u.pathname.replace(/\/+$/,'')||'/';
    if(PF13_ROUTE_ALIASES[clean])u.pathname=PF13_ROUTE_ALIASES[clean];
  }
  return u
}
function rememberCurrentForBack(){
  const cur=currentInternalUrl(),stack=pf13Stack();
  if(stack.at(-1)!==cur)stack.push(cur);
  pf13SaveStack(stack);
}
function openGame(raw){
  const u=normalizeNav(raw);
  if(!u){toast('Este jogo está com um link inválido.',true);return false}
  if(u.origin!==location.origin){location.assign(u.href);return true}
  try{sessionStorage.setItem('pf13_game_return',currentInternalUrl())}catch{}
  location.assign(u.href);
  return true
}
function navigate(raw,replace=false){
  const u=normalizeNav(raw);
  if(!u){toast('Este botão ainda não possui uma rota válida.',true);return false}
  if(u.origin!==location.origin){location.assign(u.href);return true}
  if(gamePath(u.pathname))return openGame(u.href);
  const target=u.pathname+u.search+u.hash;
  if(!owned(u.pathname)){location.assign(target);return true}
  if(!replace)rememberCurrentForBack();
  history[replace?'replaceState':'pushState']({},'',target);
  lastHref=location.href;
  render();
  return true
}
function navigateBack(fallback='/profile/account'){
  const cur=currentInternalUrl(),stack=pf13Stack();
  let target=null;
  while(stack.length){
    const candidate=stack.pop();
    if(!candidate||candidate===cur)continue;
    const u=normalizeNav(candidate);
    if(u&&u.origin===location.origin&&owned(u.pathname)){target=u.pathname+u.search+u.hash;break}
  }
  pf13SaveStack(stack);
  if(target){
    history.replaceState({},'',target);
    render();
    return;
  }
  navigate(fallback||'/',true);
}
function removeGameChrome(){
  document.getElementById('pf13-player-chrome')?.remove();
  document.body.classList.remove('pf13-player-mode');
}
function renderGameChrome(){
  if(!MOBILE()||!gamePath())return;
  setOwned(false);
  if(app())app().innerHTML='';
  document.body.classList.add('pf13-player-mode');
  if(document.getElementById('pf13-player-chrome'))return;
  const chrome=document.createElement('div');
  chrome.id='pf13-player-chrome';
  chrome.innerHTML=`<button type="button" id="pf13-player-back" aria-label="Voltar ao PixFácil">${ico('back')}<span class="pf13-player-brand">${brand()}</span></button>`;
  document.body.appendChild(chrome);
}
function requireAuth(){if(authed())return true;navigate('/login',true);return false}
async function getAccount(){if(!authed())return null;return await api('/api/profile/account')}



function retroCard(g,compact=false){
  const cover=g.cover_url||asset(g.cover)||'';
  const min=Number(g.min_bet||1);
  return `<a href="/retro/game/${encodeURIComponent(g.slug)}" data-pf8-nav class="pf14-retro-card${compact?' compact':''}">
    <div class="pf14-retro-art">${cover?`<img loading="lazy" src="${attr(cover)}" alt="${attr(g.name||'Jogo retrô')}">`:''}<span class="pf14-retro-badge">RETRO</span><span class="pf14-retro-play">${ico('game')}</span></div>
    <div class="pf14-retro-meta"><strong>${esc(g.name||g.slug)}</strong><span>A partir de ${money(min)}</span></div>
  </a>`
}
function retroSlug(){const m=path().match(/^\/retro\/(?:game|play)\/([^/?#]+)/i);return m?decodeURIComponent(m[1]):''}
function newClientEventId(){if(globalThis.crypto?.randomUUID)return globalThis.crypto.randomUUID();const b=new Uint8Array(16);if(globalThis.crypto?.getRandomValues)globalThis.crypto.getRandomValues(b);else for(let i=0;i<16;i++)b[i]=Math.floor(Math.random()*256);b[6]=(b[6]&15)|64;b[8]=(b[8]&63)|128;const h=[...b].map(x=>x.toString(16).padStart(2,'0')).join('');return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`}

async function renderRetroCatalog(seq){
  const [data,account]=await Promise.all([api('/api/retro/games'),getAccount().catch(()=>null)]);
  if(seq!==renderSeq)return;
  const games=data.games||[];
  const body=`${topbar()}${moneyRow(account)}${pageHead('Jogos Retrô','Jogos da casa com uma mecânica de aposta separada','/')}
    <section class="pf14-retro-intro"><div class="pf14-retro-pixel">${ico('game')}</div><div><strong>ARCADE PIXFÁCIL</strong><span>Escolha o jogo, defina a entrada e bata a meta da rodada.</span></div></section>
    ${games.length?`<div class="pf14-retro-grid">${games.map(g=>retroCard(g)).join('')}</div>`:empty('Nenhum jogo retrô ativo no momento.')}
    ${siteFooter()}`;
  app().innerHTML=shell(body,{active:'games'});pf11AfterPaint();
}

async function renderRetroBet(seq){
  const slug=retroSlug();
  if(!slug){navigate('/retro',true);return}
  const [data,account]=await Promise.all([api(`/api/retro/games/${encodeURIComponent(slug)}`),getAccount().catch(()=>null)]);
  if(seq!==renderSeq)return;
  const g=data.game||{};
  const min=Number(g.min_bet||1),max=Number(g.max_bet||100);
  const rawWin=new URLSearchParams(location.search).get('win_amount');
  const lastWin=rawWin!==null?Number(rawWin):null;
  const available=Number(account?.wallet?.balance||0)+Number(account?.wallet?.balance_bonus||0)+Number(account?.wallet?.balance_withdrawal||0);
  const candidates=[min,2,5,10,20,50,100].filter((v,i,a)=>v>=min&&v<=max&&a.indexOf(v)===i).slice(0,6);
  const body=`${topbar()}${pageHead(g.name||'Jogo Retrô','Defina a entrada da próxima rodada','/retro')}
    <div class="pf14-retro-bet-hero">${g.cover_url?`<img src="${attr(g.cover_url)}" alt="${attr(g.name||'')}">`:''}<div class="pf14-retro-bet-shade"></div><div class="pf14-retro-bet-title"><span>JOGO DA CASA</span><h1>${esc(g.name||slug)}</h1><p>${esc(g.description||'')}</p></div></div>
    ${Number.isFinite(lastWin)?`<div class="pf14-retro-result ${lastWin>0?'won':'lost'}"><strong>${lastWin>0?'PRÊMIO CREDITADO':'RODADA ENCERRADA'}</strong><span>${lastWin>0?money(lastWin):'A meta não foi atingida.'}</span></div>`:''}
    ${authed()?`<section class="pf8-card pf14-retro-bet-card"><div class="pf14-retro-balance"><span>Saldo disponível</span><strong>${money(available)}</strong></div><div class="pf14-retro-rules"><span>Entrada ${money(min)} – ${money(max)}</span><span>Meta ${Number(g.meta_multiplier||0).toLocaleString('pt-BR')}×</span><span>Teto ${Number(g.max_win_multiplier||0).toLocaleString('pt-BR')}×</span></div><form id="pf14-retro-start" data-retro-slug="${attr(slug)}"><div class="pf8-field"><label>Valor da aposta</label><div class="pf8-inputwrap"><span style="color:#a4ada7;font-weight:800">R$</span><input name="bet" inputmode="decimal" value="${min.toLocaleString('pt-BR',{minimumFractionDigits:2})}" required></div></div><div class="pf8-quick pf14-retro-quick">${candidates.map(v=>`<button type="button" class="pf8-q" data-retro-quick="${v}">${money(v).replace(',00','')}</button>`).join('')}</div><button class="pf8-submit pf14-retro-start" type="submit">${ico('game')} Iniciar rodada</button></form><div class="pf8-secure">${ico('shield')}<div><strong>Motor de aposta separado</strong><span>Essa rodada não altera a integração dos jogos de provedor.</span></div></div></section>`:`<section class="pf8-card"><h3 style="margin:0 0 6px;color:#fff">Entre para apostar</h3><p style="margin:0 0 12px;color:#8f9892;font-size:11px">Você pode ver os jogos, mas precisa estar logado para iniciar uma rodada.</p><a href="/login" data-pf8-nav class="pf8-submit" style="display:flex;text-decoration:none">Entrar</a></section>`}`;
  app().innerHTML=shell(body,{active:'games'});pf11AfterPaint();
}

async function renderRetroPlay(seq){
  if(!requireAuth())return;
  const slug=retroSlug();
  if(!slug){navigate('/retro',true);return}
  let d;
  try{d=await api(`/api/retro/games/${encodeURIComponent(slug)}/launch`,{method:'POST',body:{}})}catch(e){toast(e.message||'Inicie uma nova rodada.',true);navigate(`/retro/game/${encodeURIComponent(slug)}`,true);return}
  if(seq!==renderSeq)return;
  const g=d.game||{},r=d.round||{};
  const base=location.origin;
  const src=`/retro-games/${encodeURIComponent(g.slug)}/index.html?slug=${encodeURIComponent(g.slug)}&baseurl=${encodeURIComponent(base)}&velo=${encodeURIComponent(g.player_speed||1)}&back=${encodeURIComponent(`/retro/game/${g.slug}`)}`;
  const body=`<div class="pf14-player"><div class="pf14-playerbar"><button type="button" data-action="retro-forfeit" data-retro-slug="${attr(slug)}">${ico('back')}<span>${esc(g.name||'Jogo')}</span></button><div><small>APOSTA</small><strong>${money(r.bet||0)}</strong></div><div><small>META</small><strong>${money(r.meta||0)}</strong></div></div><iframe id="pf14-retro-frame" src="${attr(src)}" allow="autoplay; fullscreen; gamepad" allowfullscreen></iframe></div>`;
  app().innerHTML=shell(body,{active:'games',bottomNav:false});
  const frame=document.getElementById('pf14-retro-frame');
  frame?.addEventListener('load',async()=>{try{const u=frame.contentWindow.location;if(u.pathname.startsWith(`/retro-games/${g.slug}/`))return;if(u.pathname.startsWith(`/retro/game/${g.slug}`)){try{const last=await api(`/api/retro/games/${encodeURIComponent(g.slug)}/last-result`);const payout=Number(last?.round?.payout||0);location.replace(`/retro/game/${encodeURIComponent(g.slug)}?win_amount=${encodeURIComponent(payout)}`)}catch{location.replace(`/retro/game/${encodeURIComponent(g.slug)}`)}return}location.replace(u.pathname+u.search+u.hash)}catch{}});
}

function siteFooter(){
  const year=new Date().getFullYear();
  return `<footer class="pf131-footer">
    <div class="pf131-footer-line"></div>
    <div class="pf131-footer-brand">
      <a href="/" data-pf8-nav class="pf131-footer-logo" aria-label="PixFácil">${brand()}</a>
      <span class="pf131-age">18+</span>
    </div>
    <p class="pf131-footer-copy">Entretenimento online com uma experiência simples, segura e responsável.</p>
    <nav class="pf131-footer-links" aria-label="Links do rodapé">
      <a href="/casino/provider/all/category/all" data-pf8-nav>Jogos</a>
      <a href="/support-center" data-pf8-nav>Suporte</a>
      <a href="/profile/responsible-gaming" data-pf8-nav>Jogo responsável</a>
    </nav>
    <div class="pf131-footer-responsible">
      ${ico('shield')}
      <span>Jogue com responsabilidade. Conteúdo destinado exclusivamente a maiores de 18 anos.</span>
    </div>
    <div class="pf131-footer-bottom">
      <span>© ${year} PixFácil</span>
      <span>Todos os direitos reservados.</span>
    </div>
  </footer>`
}

function pf11GameKey(g){return String(g?.id??g?.game_code??g?.game_name??'')}
function pf11ProviderKey(g){return String(g?.provider||'sem-provedor').toLowerCase()}
function pf11Diversify(sections,limit=12,exclude=new Set()){
  const pools=(sections||[]).map(s=>({section:s,games:[...(s.games||[])]})).filter(x=>x.games.length);
  const selected=[],seen=new Set(exclude),providerCounts=new Map();
  for(const cap of [2,3,999]){
    let progress=true;
    while(progress&&selected.length<limit){
      progress=false;
      for(const pool of pools){
        if(selected.length>=limit)break;
        const index=pool.games.findIndex(g=>{const key=pf11GameKey(g);if(!key||seen.has(key))return false;return (providerCounts.get(pf11ProviderKey(g))||0)<cap});
        if(index<0)continue;
        const [g]=pool.games.splice(index,1), key=pf11GameKey(g), provider=pf11ProviderKey(g);
        seen.add(key); providerCounts.set(provider,(providerCounts.get(provider)||0)+1); selected.push(g); progress=true;
      }
    }
    if(selected.length>=limit)break;
  }
  return selected;
}
function pf11EnsureCenter(){
  const nav=document.querySelector('.pf11-bottom,.pf8-bottom'); if(!nav||nav.querySelector('[data-pf11-center]'))return;
  const a=document.createElement('a'); a.href='/profile/deposit'; a.setAttribute('data-pf8-nav',''); a.setAttribute('data-pf11-center','1'); a.setAttribute('data-pf11-slot','3'); a.className='center pf11-nav-center'; a.setAttribute('aria-label','Depositar'); a.innerHTML=`<span class="pf8-center pf11-center">${pixmark()}</span>`; nav.appendChild(a);
}
function pf11AfterPaint(){requestAnimationFrame(()=>{pf11EnsureCenter();document.querySelectorAll('.pf8-section').forEach((el,i)=>{el.classList.add('pf11-reveal');el.style.setProperty('--pf11-delay',Math.min(i*45,225)+'ms')})})}

async function renderHome(seq){
  const themeHero=CFG.pixfacilBanner||'/pixfacil-v14/hero.webp';
  const preview=`${topbar()}${moneyRow(null)}<div class="pf8-hero pf9-theme-hero pf10-hero pf11-hero"><img src="${attr(themeHero)}" data-pf12-fallback="/pixfacil-v14/hero.webp" alt="Bônus PixFácil"></div><label class="pf8-search pf11-search">${ico('search')}<input id="pf8-home-search" placeholder="Buscar jogos" autocomplete="off"></label><div class="pf8-chips pf11-chips"><a href="/casino/provider/all/category/all?sort=popular" data-pf8-nav class="pf8-chip active">${ico('fire')}Popular</a><a href="/casino/provider/all/category/slots" data-pf8-nav class="pf8-chip">${ico('slots')}Slots</a><a href="/casino/provider/all/category/live" data-pf8-nav class="pf8-chip">${ico('live')}Ao vivo</a><a href="/casino/provider/all/category/all?sort=new" data-pf8-nav class="pf8-chip">${ico('star')}Novos</a><a href="/retro" data-pf8-nav class="pf8-chip pf14-retro-chip">${ico('game')}Retrô</a></div><section class="pf8-section"><div class="pf8-sec-head"><h2>JOGOS RETRÔ</h2><a href="/retro" data-pf8-nav>VER TODOS →</a></div><div class="pf14-retro-home-skeleton"><i></i><i></i><i></i></div></section><section class="pf8-section"><div class="pf8-sec-head"><h2>PROVEDORES PREMIUM</h2><a href="/casino/provider/all/category/all" data-pf8-nav>VER TODOS →</a></div><div class="pf11-provider-skeleton"><i></i><i></i><i></i></div></section><section class="pf8-section"><div class="pf8-sec-head"><h2>JOGOS QUENTES</h2><a href="/casino/provider/all/category/all?sort=popular" data-pf8-nav>VER TODOS →</a></div><div class="pf11-game-skeleton">${'<i></i>'.repeat(9)}</div></section><div id="pf8-live-slot"></div>`;
  app().innerHTML=shell(preview,{active:'home'});pf11AfterPaint();
  const [home,providers,cats,retro,account]=await Promise.all([api('/api/home').catch(()=>({sections:[]})),api('/api/home/providers').catch(()=>({providers:[]})),api('/api/categories').catch(()=>({categories:[]})),api('/api/retro/games?home=1').catch(()=>({games:[]})),getAccount().catch(()=>null)]); if(seq!==renderSeq)return;
  const sections=home.sections||[], hot=pf11Diversify(sections,12), hotIds=new Set(hot.map(pf11GameKey));
  const newSection=sections.find(s=>s.type==='new'||/lan[cç]amento|novidade|novo/i.test(s.title||''));
  const secondary=pf11Diversify(newSection?[newSection,...sections]:sections,9,hotIds), ps=(providers.providers||[]).slice(0,10), cs=cats.categories||[];
  const findCat=rx=>cs.find(c=>rx.test(`${c.name||''} ${c.slug||''}`)), slot=findCat(/slot/i), live=findCat(/live|ao vivo/i), pg=(providers.providers||[]).find(p=>/pocket|pg soft|pgsoft|\bpg\b/i.test(`${p.name||''} ${p.code||''}`));
  const chips=[`<a href="/casino/provider/all/category/all?sort=popular" data-pf8-nav class="pf8-chip active">${ico('fire')}Popular</a>`,slot?`<a href="/casino/provider/all/category/${encodeURIComponent(slot.slug)}" data-pf8-nav class="pf8-chip">${ico('slots')}${esc(slot.name)}</a>`:'',live?`<a href="/casino/provider/all/category/${encodeURIComponent(live.slug)}" data-pf8-nav class="pf8-chip">${ico('live')}${esc(live.name)}</a>`:'',pg?`<a href="/casino/provider/${encodeURIComponent(pg.id)}/category/all" data-pf8-nav class="pf8-chip">PG Soft</a>`:'',`<a href="/casino/provider/all/category/all?sort=new" data-pf8-nav class="pf8-chip">${ico('star')}Novos</a><a href="/retro" data-pf8-nav class="pf8-chip pf14-retro-chip">${ico('game')}Retrô</a>`].filter(Boolean).join('');
  const providersBlock=ps.length?`<div class="pf8-providers pf11-providers">${ps.map(providerCard).join('')}</div>`:empty('Nenhum provedor disponível.');
  const retroGames=(retro.games||[]).slice(0,8), retroBlock=retroGames.length?`<div class="pf14-retro-home">${retroGames.map(g=>retroCard(g,true)).join('')}</div>`:empty('Nenhum jogo retrô disponível.');
  const hotBlock=hot.length?`<div class="pf8-games pf11-games pf12-hot-grid">${hot.map(g=>gameCard(g,'hot')).join('')}</div>`:empty('Nenhum jogo disponível.');
  const secondaryBlock=secondary.length>=3?sec(newSection?.title||'Novidades','/casino/provider/all/category/all?sort=new',`<div class="pf8-games pf11-games pf12-new-grid">${secondary.map(g=>gameCard(g,'new')).join('')}</div>`):'';
  const body=`${topbar()}${moneyRow(account)}<div class="pf8-hero pf9-theme-hero pf10-hero pf11-hero"><img src="${attr(themeHero)}" data-pf12-fallback="/pixfacil-v14/hero.webp" alt="Bônus PixFácil"></div><label class="pf8-search pf11-search">${ico('search')}<input id="pf8-home-search" placeholder="Buscar jogos" autocomplete="off"></label><div class="pf8-chips pf11-chips">${chips}<a href="/retro" data-pf8-nav class="pf8-chip pf14-retro-chip">${ico('game')}Retrô</a></div>${sec('Jogos Retrô','/retro',retroBlock)}${sec('Provedores Premium','/casino/provider/all/category/all',providersBlock)}${sec('Jogos Quentes','/casino/provider/all/category/all?sort=popular',hotBlock)}${secondaryBlock}<div id="pf8-live-slot"></div>${siteFooter()}`;
  app().innerHTML=shell(body,{active:'home'});pf11AfterPaint();setTimeout(extractLive,420);
}
function extractLive(){if(path()!=='/'||!document.body.classList.contains('pf8-owned'))return;const root=oldRoot();const slot=document.getElementById('pf8-live-slot');if(!root||!slot)return;const els=[...root.querySelectorAll('h1,h2,h3,h4,div,span')];const hit=els.find(e=>/ganhos ao vivo/i.test((e.textContent||'').trim())&&(e.textContent||'').trim().length<80);if(!hit)return;let box=hit;for(let i=0;i<7&&box;i++,box=box.parentElement){const txt=(box.innerText||'');if((txt.match(/R\$\s*[\d.,]+/g)||[]).length>=2&&box.querySelectorAll('img').length>=2&&txt.length<5000)break}if(!box)return;const cards=[];const seen=new Set();for(const el of box.querySelectorAll('div,li,article')){const img=el.querySelector('img');const txt=(el.innerText||'').replace(/\s+/g,' ').trim();const vals=txt.match(/R\$\s*[\d.,]+/g)||[];if(!img||!vals.length||txt.length>320||txt.length<15)continue;const key=img.src+'|'+vals.at(-1);if(seen.has(key))continue;seen.add(key);let user=txt.split(/R\$/)[0].trim().split(/\s+/).slice(0,3).join(' ');cards.push({img:img.src,user:user||'Jogador',amount:vals.at(-1)});if(cards.length>=5)break}if(cards.length<2)return;slot.innerHTML=sec('Ganhos ao vivo','',`<div class="pf8-live">${cards.map(c=>`<div class="pf8-live-card"><img src="${attr(c.img)}" alt=""><div><div class="pf8-live-user">${esc(c.user)}</div><small>premiação em tempo real</small></div><strong>${esc(c.amount)}</strong></div>`).join('')}</div>`)}

async function renderLogin(seq){if(authed()){navigate('/',true);return}if(seq!==renderSeq)return;const body=`<div class="pf8-auth-brand"><a href="/" data-pf8-nav class="pf8-brand">${brand()}</a></div><section class="pf8-auth-card"><div class="pf8-auth-icon">${ico('user')}</div><h1 class="pf8-auth-title">Bem-vindo de <b>volta!</b></h1><p class="pf8-auth-sub">Faça login para continuar</p><form id="pf8-login"><div class="pf8-field"><label>E-mail</label><div class="pf8-inputwrap">${ico('user')}<input name="email" type="email" autocomplete="email" placeholder="Digite seu e-mail" required></div></div><div class="pf8-field"><label>Senha</label><div class="pf8-inputwrap">${ico('shield')}<input name="password" type="password" autocomplete="current-password" placeholder="Digite sua senha" required><button type="button" data-action="toggle-pass">${ico('eye')}</button></div></div><div style="text-align:right;margin:-2px 0 13px"><a href="/forget-password" data-pf8-nav style="color:var(--pf8-green);font-size:11px;text-decoration:none">Esqueci minha senha</a></div><button class="pf8-submit" type="submit">Entrar</button></form><div class="pf8-divider">ou</div><a href="/register" data-pf8-nav class="pf8-alt">Criar conta</a><div class="pf8-secure">${ico('shield')}<div><strong>Seus dados estão protegidos</strong><span>Ambiente seguro e criptografado</span></div></div></section>`;app().innerHTML=shell(body,{auth:true,bottomNav:false});}
async function renderRegister(seq){if(authed()){navigate('/',true);return}if(seq!==renderSeq)return;const body=`<div class="pf8-auth-brand"><a href="/" data-pf8-nav class="pf8-brand">${brand()}</a></div><section class="pf8-auth-card"><div class="pf8-auth-icon">${pixmark()}</div><h1 class="pf8-auth-title">Criar <b>conta</b></h1><p class="pf8-auth-sub">Preencha seus dados para começar a jogar.</p><form id="pf8-register"><div class="pf8-field"><div class="pf8-inputwrap">${ico('user')}<input name="name" placeholder="Nome completo" required></div></div><div class="pf8-field"><div class="pf8-inputwrap">${ico('user')}<input name="email" type="email" placeholder="E-mail" required></div></div><div class="pf8-field"><div class="pf8-inputwrap">${ico('support')}<input name="phone" inputmode="tel" placeholder="Telefone" required></div></div><div class="pf8-field"><div class="pf8-inputwrap">${ico('shield')}<input name="password" type="password" autocomplete="new-password" placeholder="Senha (mín. 8, maiúscula e número)" required><button type="button" data-action="toggle-pass">${ico('eye')}</button></div></div><details style="margin:2px 0 12px;color:#929a95;font-size:11px"><summary style="cursor:pointer;color:var(--pf8-green)">Tenho um cupom</summary><div class="pf8-inputwrap" style="margin-top:8px"><input name="cupom" placeholder="Cupom (opcional)"></div></details><label style="display:flex;gap:8px;align-items:flex-start;color:#a7aea9;font-size:10px;line-height:1.45;margin:8px 0 14px"><input type="checkbox" required style="accent-color:var(--pf8-green);margin-top:2px"> Li e concordo com os Termos de Uso e a Política de Privacidade.</label><button class="pf8-submit" type="submit">Cadastrar</button></form><div class="pf8-divider">ou</div><a href="/login" data-pf8-nav class="pf8-alt">Já tenho conta · Fazer login</a></section>`;app().innerHTML=shell(body,{auth:true,bottomNav:false});}
async function renderForgot(seq){if(seq!==renderSeq)return;const reset=/^\/reset-password\//i.test(path());const tok=reset?decodeURIComponent(path().split('/').pop()):'';const body=`<div class="pf8-auth-brand"><a href="/" data-pf8-nav class="pf8-brand">${brand()}</a></div><section class="pf8-auth-card"><div class="pf8-auth-icon">${ico('shield')}</div><h1 class="pf8-auth-title">${reset?'Nova <b>senha</b>':'Recuperar <b>senha</b>'}</h1><p class="pf8-auth-sub">${reset?'Defina uma nova senha para sua conta.':'Informe seu e-mail para receber o link de recuperação.'}</p><form id="${reset?'pf8-reset':'pf8-forgot'}">${reset?`<div class="pf8-field"><div class="pf8-inputwrap"><input name="email" type="email" placeholder="E-mail" required></div></div><div class="pf8-field"><div class="pf8-inputwrap"><input name="password" type="password" placeholder="Nova senha" required></div></div><div class="pf8-field"><div class="pf8-inputwrap"><input name="password_confirmation" type="password" placeholder="Confirmar senha" required></div></div><input type="hidden" name="token" value="${attr(tok)}">`:`<div class="pf8-field"><div class="pf8-inputwrap"><input name="email" type="email" placeholder="E-mail" required></div></div>`}<button class="pf8-submit" type="submit">${reset?'Salvar nova senha':'Enviar link'}</button></form><div style="margin-top:16px;text-align:center"><a href="/login" data-pf8-nav style="color:var(--pf8-green);font-size:11px;text-decoration:none">Voltar ao login</a></div></section>`;app().innerHTML=shell(body,{auth:true,bottomNav:false});}

function parseCasinoRoute(){const p=path();const m=p.match(/^\/casino\/provider\/([^/]+)\/category\/([^/]+)/i);const q=new URLSearchParams(location.search);return {provider:m?m[1]:'all',category:m?m[2]:'all',sort:q.get('sort')||'popular',search:q.get('q')||''}}
async function renderCasino(seq){const r=parseCasinoRoute();const [cats,providers,data]=await Promise.all([api('/api/categories').catch(()=>({categories:[]})),api('/api/home/providers').catch(()=>({providers:[]})),api(`/api/casinos/games?provider=${encodeURIComponent(r.provider)}&category=${encodeURIComponent(r.category)}&sort=${encodeURIComponent(r.sort)}&searchTerm=${encodeURIComponent(r.search)}&page=1`)]);if(seq!==renderSeq)return;casinoState={...r,page:1,games:data.games?.data||[],last:data.games?.last_page||1,cats:cats.categories||[],providers:providers.providers||[]};renderCasinoUI()}
function renderCasinoUI(){const s=casinoState;const chips=[`<button class="pf8-chip ${s.category==='all'?'active':''}" data-casino-category="all">${ico('fire')}Todos</button>`,...s.cats.slice(0,10).map(c=>`<button class="pf8-chip ${String(s.category)===String(c.slug)?'active':''}" data-casino-category="${attr(c.slug)}">${esc(c.name)}</button>`)].join('');const body=`${topbar()}<label class="pf8-search">${ico('search')}<input id="pf8-casino-search" value="${attr(s.search)}" placeholder="Buscar jogos e provedores"></label><div class="pf8-chips">${chips}</div>${sec('Jogos em destaque','',`<div id="pf8-casino-grid" class="pf8-games">${s.games.map(gameCard).join('')}</div>${s.page<s.last?`<button class="pf8-loadmore" data-action="casino-more">Ver mais jogos</button>`:''}`)}`;app().innerHTML=shell(body,{active:'games'});}
async function casinoLoad(reset=true){if(!casinoState)return;const s=casinoState;if(reset){s.page=1;s.games=[]}else s.page++;const d=await api(`/api/casinos/games?provider=${encodeURIComponent(s.provider)}&category=${encodeURIComponent(s.category)}&sort=${encodeURIComponent(s.sort)}&searchTerm=${encodeURIComponent(s.search)}&page=${s.page}`);s.last=d.games?.last_page||1;s.games.push(...(d.games?.data||[]));renderCasinoUI()}

async function renderDeposit(seq){if(!requireAuth())return;const [account,settings]=await Promise.all([getAccount(),api('/api/settings/data').catch(()=>({setting:{}}))]);if(seq!==renderSeq)return;const min=Number(account?.limits?.deposit?.min_deposit||10),max=Number(account?.limits?.deposit?.max_deposit||0),cpf=account?.user?.cpf||account?.user?.document||'';const quick=[20,50,100,200].filter(v=>v>=min&&(max<=0||v<=max));const body=`${topbar()}${pageHead('Depositar','Deposite via PIX e comece a jogar')}<div class="pf8-money-card"><div class="label">Saldo disponível</div><div class="value">${money(account?.wallet?.balance||0)}</div><div class="pf8-money-art">${pixmark()}</div></div><section class="pf8-card"><h2 style="margin:0 0 9px;color:#fff;font-size:16px">Valores rápidos</h2><div class="pf8-quick">${quick.map(v=>`<button class="pf8-q" data-deposit-quick="${v}">${money(v).replace(',00','')}</button>`).join('')}</div><form id="pf8-deposit"><div class="pf8-field"><label>Escolha o valor</label><div class="pf8-inputwrap"><span style="color:#a4ada7;font-weight:800">R$</span><input name="amount" inputmode="decimal" placeholder="0,00" value="${min.toLocaleString('pt-BR',{minimumFractionDigits:2})}" required></div><small>Valor mínimo: ${money(min)}${max>0?' · máximo: '+money(max):''}</small></div>${cpf?'':`<div class="pf8-field"><label>CPF</label><div class="pf8-inputwrap"><input name="cpf" inputmode="numeric" placeholder="000.000.000-00" required></div></div>`}<button class="pf8-submit" type="submit">${pixmark()} Gerar PIX</button><div class="pf8-secure">${ico('shield')}<div><strong>Transação 100% segura</strong><span>Seu saldo é atualizado automaticamente após a confirmação.</span></div></div></form></section><div id="pf8-pix-result"></div>`;app().innerHTML=shell(body,{active:'deposit'});app().dataset.depositGateway=settings?.setting?.deposit_gateway||'';app().dataset.cpf=cpf;}
function renderPixResult(d){const box=document.getElementById('pf8-pix-result');if(!box)return;let qr=d?.qrcode64||'';if(qr&&!/^data:/i.test(qr))qr='data:image/png;base64,'+qr;box.innerHTML=`<section class="pf8-paybox"><div class="pf8-paytop"><div><h3>Pagamento via PIX</h3><p>Escaneie o QR Code ou copie o código PIX</p></div><div class="pf8-expire">Aguardando pagamento...</div></div>${qr?`<img class="pf8-qr" src="${attr(qr)}" alt="QR Code PIX">`:''}<div class="pf8-code"><code>${esc(d?.qrcode||'')}</code><button class="pf8-copy" data-copy="${attr(d?.qrcode||'')}">${ico('copy')} Copiar</button></div><div class="pf8-secure">${ico('shield')}<div><strong>Após o pagamento</strong><span>O saldo será creditado automaticamente.</span></div></div></section>`;if(pollTimer)clearInterval(pollTimer);if(d?.idTransaction){pollTimer=setInterval(async()=>{try{const st=await api('/api/carteira_wallet/deposit/status',{method:'POST',body:{idTransaction:d.idTransaction}});if(st?.status==='PAID'){clearInterval(pollTimer);pollTimer=null;toast('PIX confirmado! Saldo atualizado.');document.querySelector('.pf8-expire').textContent='Pagamento confirmado ✓'}else if(st?.status==='CANCELLED'){clearInterval(pollTimer);pollTimer=null;toast('Este PIX foi cancelado.',true)}}catch{}},5000)}}

async function renderWithdraw(seq){if(!requireAuth())return;const [account,keys]=await Promise.all([getAccount(),api('/api/profile/pix-keys')]);if(seq!==renderSeq)return;const min=Number(account?.limits?.withdrawal?.min_withdrawal||0),max=Number(account?.limits?.withdrawal?.max_withdrawal||0),available=Number(account?.wallet?.balance_withdrawal||0);const list=keys.pix_keys||[];const keyHtml=list.length?`<div class="pf8-keylist">${list.map((k,i)=>`<label class="pf8-key ${i===0?'active':''}"><input type="radio" name="pix_key_id" value="${attr(k.id)}" ${i===0?'checked':''}><div><strong>CPF · ${esc(k.holder_name||account?.user?.name||'Titular')}</strong><span>${esc(k.pix_key||k.holder_cpf||'')}</span></div></label>`).join('')}</div>`:`<div class="pf8-card" id="pf8-key-create"><h3 style="margin:0 0 5px;color:#fff;font-size:15px">Cadastre sua chave PIX</h3><p style="margin:0 0 11px;color:#929a95;font-size:11px">Este sistema usa o CPF do titular como chave de saque.</p><form id="pf8-create-key"><div class="pf8-inputwrap"><input name="cpf" inputmode="numeric" placeholder="CPF do titular" value="${attr(account?.user?.cpf||'')}" required></div><button class="pf8-mission-btn ready" style="width:100%;margin-top:9px" type="submit">Cadastrar chave</button></form></div>`;const quick=[50,100,200,500,1000].filter(v=>v>=min&&v<=Math.max(available,max||available));const body=`${topbar()}${pageHead('Sacar','Transfira seus ganhos via PIX')}<div class="pf8-money-card"><div class="label">Saldo disponível para saque</div><div class="value">${money(available)}</div><div class="pf8-money-art">${ico('wallet')}</div></div><section class="pf8-card"><h2 style="margin:0 0 5px;color:#fff;font-size:17px">Chave PIX</h2><p style="margin:0 0 11px;color:#929a95;font-size:11px">Selecione a chave cadastrada</p>${keyHtml}</section>${list.length?`<section class="pf8-card"><form id="pf8-withdraw"><div class="pf8-field"><label>Valor do saque</label><div class="pf8-inputwrap"><span style="color:#a4ada7;font-weight:800">R$</span><input name="amount" inputmode="decimal" placeholder="0,00" required><button type="button" data-action="withdraw-max" style="color:var(--pf8-green);font-size:10px;font-weight:850">MÁXIMO</button></div><small>Mínimo ${money(min)}${max>0?' · máximo '+money(max):''}</small></div><div class="pf8-quick">${quick.map(v=>`<button type="button" class="pf8-q" data-withdraw-quick="${v}">${money(v).replace(',00','')}</button>`).join('')}</div><div class="pf8-secure">${ico('shield')}<div><strong>Seus dados estão protegidos</strong><span>Os saques são processados com segurança.</span></div></div><button class="pf8-submit" type="submit">${ico('withdraw')} Solicitar saque</button></form></section>`:''}`;app().innerHTML=shell(body,{active:'profile'});app().dataset.withdrawAvailable=String(available);}

async function renderProfile(seq){if(!requireAuth())return;const [a,vips]=await Promise.all([getAccount(),api('/api/vips/').catch(()=>[])]);if(seq!==renderSeq)return;const u=a.user||{},w=a.wallet||{};const vip=(vips||[]).filter(v=>v.eligible).at(-1)||(vips||[])[0];const initials=(u.name||'U').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase();const menu=[['wallet','Minha carteira','Gerencie seus saldos e movimentações','/profile/transactions'],['history','Histórico de apostas','Consulte suas apostas recentes','/profile/bets'],['users','Afiliados','Convide amigos e acompanhe comissões','/profile/affiliate'],['shield','Verificação da conta','Documentos e segurança da sua conta','/profile/verification'],['target','Jogo responsável','Limites e uso consciente','/profile/responsible-gaming'],['support','Suporte','Central de ajuda PixFácil','/support-center']];const body=`${topbar()}<div class="pf8-profile-hero"><div class="pf8-avatar">${esc(initials)}</div><div><div class="pf8-profile-name">${esc(u.name||'Minha conta')}</div><div class="pf8-id">ID: ${esc(u.id||'')}</div>${vip?`<span class="pf8-vip-pill">♛ ${esc(vip.title)}</span>`:''}</div></div><div class="pf8-statgrid"><div class="pf8-stat"><small>Saldo disponível</small><strong>${money(w.balance||0)}</strong></div><div class="pf8-stat green"><small>Bônus disponível</small><strong>${money(w.balance_bonus||0)}</strong></div><div class="pf8-stat gold"><small>Recompensas</small><strong>${money(w.refer_rewards||0)}</strong></div></div><div class="pf8-actions2"><a href="/profile/deposit" data-pf8-nav class="green">${ico('deposit')}<div><strong>Depositar</strong><span>Fazer um depósito</span></div></a><a href="/profile/withdraw" data-pf8-nav class="dark">${ico('withdraw')}<div><strong>Sacar</strong><span>Retirar saldo</span></div></a></div><div class="pf8-menu">${menu.map(([i,t,s,h])=>`<a href="${attr(h)}" data-pf8-nav><span class="mi">${ico(i)}</span><span class="mtxt"><strong>${esc(t)}</strong><span>${esc(s)}</span></span><span class="arrow">›</span></a>`).join('')}<button data-action="logout"><span class="mi">${ico('logout')}</span><span class="mtxt"><strong>Sair da conta</strong><span>Encerrar sessão com segurança</span></span><span class="arrow">›</span></button></div>`;app().innerHTML=shell(body,{active:'profile'});}


async function renderAffiliate(seq){
  if(!requireAuth())return;
  const d=await api('/api/profile/affiliates/').catch(()=>({status:false}));
  if(seq!==renderSeq)return;
  const refs=Array.isArray(d.referrals)?d.referrals:[];
  const rewards=Number(d.wallet?.refer_rewards||0);
  const link=d.url||'';
  const body=`${topbar()}${pageHead('Afiliados','Convide amigos e acompanhe suas comissões','/profile/account')}
    <section class="pf8-card">
      <div class="pf13-affiliate-stats"><div><small>Indicações</small><strong>${Number(d.indications||0)}</strong></div><div><small>Comissões</small><strong>${money(rewards)}</strong></div></div>
      ${d.code?`<div class="pf8-field"><label>Seu link de indicação</label><div class="pf8-code"><code>${esc(link)}</code><button type="button" class="pf8-copy" data-copy="${attr(link)}">${ico('copy')} Copiar</button></div></div><p class="pf13-note">CPA atual: ${Number(d.affiliate_cpa_percent||0).toLocaleString('pt-BR')}% · qualificação a partir de ${money(d.affiliate_baseline||0)}.</p>`:`<button type="button" class="pf8-submit" data-action="affiliate-generate">Gerar meu link de afiliado</button>`}
    </section>
    ${sec('Indicações','',refs.length?`<div class="pf8-menu">${refs.slice(0,30).map(r=>`<div class="pf13-ref-row"><span class="mi">${ico('user')}</span><span class="mtxt"><strong>${esc(r.name||'Usuário')}</strong><span>${esc(r.email||'')} · ${r.qualified?'Qualificado':'Aguardando qualificação'}</span></span><strong class="${r.qualified?'ok':''}">${money(r.commission||0)}</strong></div>`).join('')}</div>`:empty('Você ainda não possui indicações.'))}`;
  app().innerHTML=shell(body,{active:'profile'});
}

async function renderVerification(seq){
  if(!requireAuth())return;
  const d=await api('/api/profile/verification');
  if(seq!==renderSeq)return;
  const v=d.verification||null,status=String(v?.status||'').toLowerCase();
  let content='';
  if(status==='approved'){
    content=`<div class="pf13-status approved">${ico('check')}<div><strong>Conta verificada</strong><span>Sua verificação foi aprovada.</span></div></div>`;
  }else if(status==='pending'){
    content=`<div class="pf13-status pending">${ico('history')}<div><strong>Em análise</strong><span>Seus documentos estão sendo analisados.</span></div></div>`;
  }else{
    content=`${status==='rejected'?`<div class="pf13-status rejected">${ico('shield')}<div><strong>Verificação recusada</strong><span>${esc(v?.motivo||'Revise os documentos e tente novamente.')}</span></div></div>`:''}
      <form id="pf13-verification">
        <div class="pf8-field"><label>Nome completo</label><div class="pf8-inputwrap"><input name="nome_completo" value="${attr(d.user?.nome||'')}" required></div></div>
        <div class="pf8-field"><label>CPF</label><div class="pf8-inputwrap"><input name="cpf" inputmode="numeric" value="${attr(String(d.user?.cpf||'').replace(/\D/g,''))}" placeholder="Somente números" required></div></div>
        <div class="pf13-upload-grid">
          <label class="pf13-upload"><span>${ico('user')}</span><strong>Selfie</strong><small>Foto atual do rosto</small><input type="file" name="selfie" accept="image/*,.heic,.heif" required></label>
          <label class="pf13-upload"><span>${ico('shield')}</span><strong>Documento - frente</strong><small>RG ou CNH</small><input type="file" name="doc_frente" accept="image/*,.heic,.heif" required></label>
          <label class="pf13-upload"><span>${ico('shield')}</span><strong>Documento - verso</strong><small>Verso do documento</small><input type="file" name="doc_verso" accept="image/*,.heic,.heif" required></label>
        </div>
        <button class="pf8-submit" type="submit">Enviar verificação</button>
      </form>`;
  }
  app().innerHTML=shell(`${topbar()}${pageHead('Verificação','Proteção e segurança da sua conta','/profile/account')}<section class="pf8-card">${content}</section>`,{active:'profile'});
}

async function renderResponsibleGaming(seq){
  if(!requireAuth())return;
  const a=await getAccount().catch(()=>null);
  if(seq!==renderSeq)return;
  const dep=a?.limits?.deposit||{},wit=a?.limits?.withdrawal||{};
  const body=`${topbar()}${pageHead('Jogo responsável','Informação e controle para uma experiência consciente','/profile/account')}
    <section class="pf8-card"><div class="pf13-responsible-head">${ico('target')}<div><strong>Jogue com controle</strong><span>Defina orçamento e tempo antes de começar.</span></div></div></section>
    <div class="pf8-statgrid pf13-limit-grid"><div class="pf8-stat"><small>Depósito mínimo</small><strong>${money(dep.min_deposit||0)}</strong></div><div class="pf8-stat"><small>Saque mínimo</small><strong>${money(wit.min_withdrawal||0)}</strong></div></div>
    <section class="pf8-card pf13-responsible-list"><div>${ico('check')}<span>Estabeleça limites pessoais de tempo e valor.</span></div><div>${ico('check')}<span>Não tente recuperar perdas aumentando apostas.</span></div><div>${ico('check')}<span>Faça pausas frequentes durante sessões longas.</span></div><div>${ico('check')}<span>Procure ajuda se o jogo deixar de ser entretenimento.</span></div></section>`;
  app().innerHTML=shell(body,{active:'profile'});
}

async function renderSupport(seq){
  if(seq!==renderSeq)return;
  const body=`${topbar()}${pageHead('Suporte','Central de ajuda PixFácil','/profile/account')}
    <section class="pf8-card">
      <div class="pf13-support-item">${ico('deposit')}<div><strong>Depósitos e PIX</strong><span>A confirmação é automática e pode levar alguns instantes.</span></div></div>
      <div class="pf13-support-item">${ico('withdraw')}<div><strong>Saques</strong><span>Confira sua chave PIX e o saldo disponível antes de solicitar.</span></div></div>
      <div class="pf13-support-item">${ico('shield')}<div><strong>Verificação</strong><span>Acompanhe seus documentos diretamente na tela de Verificação.</span></div></div>
      <div class="pf13-support-item">${ico('game')}<div><strong>Jogos</strong><span>Se um jogo falhar, retorne ao cassino e tente novamente.</span></div></div>
    </section><a href="/profile/account" data-pf8-nav class="pf8-alt">Voltar para minha conta</a>`;
  app().innerHTML=shell(body,{active:'profile'});
}

async function renderHistory(seq,kind='transactions'){if(!requireAuth())return;const d=await api(`/api/profile/${kind}?per_page=30`);if(seq!==renderSeq)return;const isB=kind==='bets';const rows=isB?(d.bets?.data||[]):(d.transactions?.data||[]);const title=isB?'Apostas':'Histórico';const body=`${topbar()}${pageHead(title,isB?'Suas apostas recentes':'Depósitos e saques da sua conta')}<div class="pf8-chips" style="margin-bottom:12px"><a href="/profile/transactions" data-pf8-nav class="pf8-chip ${!isB?'active':''}">Transações</a><a href="/profile/bets" data-pf8-nav class="pf8-chip ${isB?'active':''}">Apostas</a></div><div class="pf8-menu">${rows.length?rows.map(r=>`<div style="min-height:67px;display:flex;align-items:center;gap:11px;padding:11px 13px;border-bottom:1px solid #242c27"><span class="mi">${ico(isB?'game':(r.type==='deposit'?'deposit':'withdraw'))}</span><span class="mtxt" style="flex:1"><strong>${esc(isB?(r.game_name||'Jogo'):(r.type==='deposit'?'Depósito':'Saque'))}</strong><span>${dateBR(r.created_at)} · ${esc(r.status||'')}</span></span><strong style="color:${(!isB&&r.type==='withdrawal')|| (isB&&r.type==='bet')?'#ff7b85':'var(--pf8-green)'}">${((!isB&&r.type==='withdrawal')||(isB&&r.type==='bet'))?'- ':'+ '}${money(r.amount)}</strong></div>`).join(''):empty('Nenhum registro encontrado.')}</div>`;app().innerHTML=shell(body,{active:'profile'});}

async function renderBonus(seq){if(!requireAuth())return;const [daily,vips,missions]=await Promise.all([api('/api/daily-bonus/check').catch(()=>null),api('/api/vips/').catch(()=>[]),api('/api/missions/').catch(()=>[])]);if(seq!==renderSeq)return;const cur=(vips||[]).filter(v=>v.eligible).at(-1)||(vips||[])[0];const next=(vips||[]).find(v=>!v.eligible);const body=`${topbar()}<div style="padding:5px 0 3px"><h1 style="margin:0;color:#fff;font-size:29px;letter-spacing:-1px">Bônus <b style="color:var(--pf8-green)">& VIP</b></h1><p style="margin:5px 0 13px;color:#929a95;font-size:12px">Mais benefícios, mais conquistas, mais chances de ganhar.</p></div><div class="pf8-bonus-hero"><span class="k">BÔNUS DIÁRIO</span><h2>${daily?money(daily.bonus_value):'Bônus'} <b>GRÁTIS</b></h2><p>${daily?.message||'Recompensas especiais para sua conta.'}</p>${daily?.can_claim?`<button data-action="daily-claim" class="cta" style="border:0">RESGATAR AGORA</button>`:''}</div>${cur?`<div class="pf8-vipbox"><div class="pf8-viprow"><div class="pf8-vipicon">VIP</div><div><h3>${esc(cur.title)}</h3><p>${next?`Progresso para ${esc(next.title)}`:'Nível máximo alcançado'}</p></div></div><div class="pf8-progress"><span style="width:${Math.min(100,Number(cur.progress||0))}%"></span></div>${cur.eligible&&!cur.claimed?`<button class="pf8-mission-btn ready" data-vip-claim="${attr(cur.id)}">Resgatar recompensa semanal · ${money(cur.weekly_reward)}</button>`:''}</div>`:''}${sec('Missões diárias','',missions.length?`<div class="pf8-missions">${missions.slice(0,8).map(m=>`<div class="pf8-mission"><div class="pf8-mission-top"><span class="pf8-mission-icon">${ico(m.type==='deposit'?'deposit':m.type==='bet'?'target':'trophy')}</span><div><h4>${esc(m.title)}</h4><p>${esc(m.description||'')}</p></div><span class="pf8-mission-reward">${money(m.reward)}</span></div><div class="pf8-progress"><span style="width:${Math.min(100,Number(m.progress||0))}%"></span></div>${m.completed?`<button class="pf8-mission-btn ready" data-mission-redeem="${attr(m.id)}">RESGATAR</button>`:''}</div>`).join('')}</div>`:empty('Nenhuma missão ativa.'))}<div style="margin-top:12px"><a href="/vip" data-pf8-nav class="pf8-alt">Ver todos os níveis VIP</a></div>`;app().innerHTML=shell(body,{active:'bonus'});}
async function renderVip(seq){if(!requireAuth())return;const vips=await api('/api/vips/');if(seq!==renderSeq)return;const body=`${topbar()}${pageHead('Clube VIP','Evolua de nível e desbloqueie recompensas semanais')}<div class="pf8-missions">${(vips||[]).map(v=>`<div class="pf8-mission"><div class="pf8-mission-top"><span class="pf8-mission-icon">${ico('crown')}</span><div><h4>${esc(v.title)}</h4><p>${esc(v.description||'')} · ${Number(v.required_missions||0)} pontos</p></div><span class="pf8-mission-reward">${money(v.weekly_reward)}</span></div><div class="pf8-progress"><span style="width:${Math.min(100,Number(v.progress||0))}%"></span></div>${v.eligible&&!v.claimed?`<button class="pf8-mission-btn ready" data-vip-claim="${attr(v.id)}">RESGATAR RECOMPENSA</button>`:''}</div>`).join('')}</div>`;app().innerHTML=shell(body,{active:'bonus'});}
async function renderPromotions(seq){const promos=await api('/api/promocoes').catch(()=>[]);if(seq!==renderSeq)return;const body=`${topbar()}<div style="padding:6px 0 13px;position:relative"><h1 style="margin:0;color:#fff;font-size:30px;letter-spacing:-1.1px">Promoções</h1><p style="margin:5px 0 0;color:#929a95;font-size:12px">Aproveite as melhores ofertas e ganhe ainda mais!</p></div><div class="pf8-promos">${promos.length?promos.map(p=>`<article class="pf8-promo">${p.imagem?`<img src="${attr(asset(p.imagem))}" alt="${attr(p.titulo||'Promoção')}">`:''}<div class="pf8-promo-body"><h3>${esc(p.titulo||'Promoção')}</h3><p>${esc(strip(p.regras_html||p.descricao||'').slice(0,150))}</p>${p.link?`<a href="${attr(p.link)}">Participar</a>`:''}</div></article>`).join(''):empty('Nenhuma promoção disponível no momento.')}</div>`;app().innerHTML=shell(body,{active:'bonus'});}

function pf11AfterPaint(){
  requestAnimationFrame(()=>{
    document.querySelectorAll('.pf8-section').forEach((el,i)=>{
      el.classList.add('pf10-reveal');
      el.style.setProperty('--pf10-delay',Math.min(i*55,280)+'ms');
    });
  });
}
async function render(){
  const seq=++renderSeq;
  if(pollTimer){clearInterval(pollTimer);pollTimer=null}

  if(gamePath()){
    renderGameChrome();
    return;
  }

  removeGameChrome();

  if(!owned()){
    setOwned(false);
    return;
  }

  setOwned(true);
  try{
    const p=path();
    if(p==='/')await renderHome(seq);
    else if(/^\/login/i.test(p))await renderLogin(seq);
    else if(/^\/register/i.test(p))await renderRegister(seq);
    else if(/^\/(?:forget-password|forgot-password|reset-password)/i.test(p))await renderForgot(seq);
    else if(/^\/profile\/deposit/i.test(p))await renderDeposit(seq);
    else if(/^\/profile\/withdraw/i.test(p))await renderWithdraw(seq);
    else if(/^\/profile\/account/i.test(p))await renderProfile(seq);
    else if(/^\/profile\/transactions/i.test(p))await renderHistory(seq,'transactions');
    else if(/^\/profile\/bets/i.test(p))await renderHistory(seq,'bets');
    else if(/^\/profile\/affiliate/i.test(p))await renderAffiliate(seq);
    else if(/^\/profile\/verification/i.test(p))await renderVerification(seq);
    else if(/^\/profile\/responsible-gaming/i.test(p))await renderResponsibleGaming(seq);
    else if(/^\/support-center/i.test(p))await renderSupport(seq);
    else if(/^\/retro\/play\//i.test(p))await renderRetroPlay(seq);
    else if(/^\/retro\/game\//i.test(p))await renderRetroBet(seq);
    else if(/^\/retro(?:\/)?$/i.test(p))await renderRetroCatalog(seq);
    else if(/^\/casino/i.test(p)||/^\/(?:pesquisar|search)/i.test(p))await renderCasino(seq);
    else if(/^\/bonus/i.test(p))await renderBonus(seq);
    else if(/^\/vip/i.test(p))await renderVip(seq);
    else if(/^\/(?:promotion|promotions|promocoes)/i.test(p))await renderPromotions(seq);
    else setOwned(false);
    pf11AfterPaint();
  }catch(e){
    if(seq!==renderSeq)return;
    if(e?.status===401){navigate('/login',true);return}
    app().innerHTML=shell(`${topbar()}${errBlock(e)}`,{active:'home'});
  }
}
let searchDebounce=null;
app()?.addEventListener('click',async e=>{const a=e.target.closest('a[data-pf8-nav]');if(a){e.preventDefault();navigate(a.getAttribute('href'));return}const g=e.target.closest('a[data-pf8-game]');if(g){e.preventDefault();openGame(g.getAttribute('href')||g.href);return}const c=e.target.closest('[data-casino-category]');if(c&&casinoState){casinoState.category=c.dataset.casinoCategory;history.replaceState({},'',`/casino/provider/${casinoState.provider}/category/${casinoState.category}?sort=${casinoState.sort}`);await casinoLoad(true);return}const q=e.target.closest('[data-deposit-quick]');if(q){e.preventDefault();const inp=document.querySelector('#pf8-deposit [name=amount]');if(inp)inp.value=Number(q.dataset.depositQuick).toLocaleString('pt-BR',{minimumFractionDigits:2});document.querySelectorAll('[data-deposit-quick]').forEach(x=>x.classList.toggle('active',x===q));return}const rq=e.target.closest('[data-retro-quick]');if(rq){e.preventDefault();const inp=document.querySelector('#pf14-retro-start [name=bet]');if(inp)inp.value=Number(rq.dataset.retroQuick).toLocaleString('pt-BR',{minimumFractionDigits:2});document.querySelectorAll('[data-retro-quick]').forEach(x=>x.classList.toggle('active',x===rq));return}const wq=e.target.closest('[data-withdraw-quick]');if(wq){e.preventDefault();const inp=document.querySelector('#pf8-withdraw [name=amount]');if(inp)inp.value=Number(wq.dataset.withdrawQuick).toLocaleString('pt-BR',{minimumFractionDigits:2});return}const cp=e.target.closest('[data-copy]');if(cp){e.preventDefault();try{await navigator.clipboard.writeText(cp.dataset.copy||'');toast('Código PIX copiado.')}catch{toast('Não foi possível copiar.',true)}return}const vip=e.target.closest('[data-vip-claim]');if(vip){e.preventDefault();try{await api(`/api/vips/${vip.dataset.vipClaim}/claim`,{method:'POST',body:{}});toast('Recompensa VIP resgatada!');render()}catch(x){toast(x.message,true)}return}const mis=e.target.closest('[data-mission-redeem]');if(mis){e.preventDefault();try{await api(`/api/missions/${mis.dataset.missionRedeem}/redeem`,{method:'POST',body:{}});toast('Recompensa resgatada!');render()}catch(x){toast(x.message,true)}return}const act=e.target.closest('[data-action]');if(!act)return;const type=act.dataset.action;if(type==='back'){navigateBack(act.dataset.backFallback||'/profile/account')}else if(type==='reload')render();else if(type==='toggle-balance'){const txt=document.querySelector('.pf8-balance-text');if(txt){if(txt.dataset.hidden==='1'){txt.textContent=txt.dataset.real||'';txt.dataset.hidden='0';act.innerHTML=ico('eye')}else{txt.dataset.real=txt.textContent;txt.textContent='R$ ••••••';txt.dataset.hidden='1';act.innerHTML=ico('eyeoff')}}}else if(type==='toggle-pass'){const inp=act.parentElement.querySelector('input');if(inp){inp.type=inp.type==='password'?'text':'password';act.innerHTML=ico(inp.type==='password'?'eye':'eyeoff')}}else if(type==='casino-more'){try{await casinoLoad(false)}catch(x){toast(x.message,true)}}else if(type==='withdraw-max'){const inp=document.querySelector('#pf8-withdraw [name=amount]');if(inp)inp.value=Number(app().dataset.withdrawAvailable||0).toLocaleString('pt-BR',{minimumFractionDigits:2})}else if(type==='affiliate-generate'){try{await api('/api/profile/affiliates/generate');toast('Seu link de afiliado foi gerado.');render()}catch(x){toast(x.message||'Não foi possível gerar o link.',true)}}else if(type==='retro-forfeit'){const slug=act.dataset.retroSlug||retroSlug();try{await api(`/api/retro/games/${encodeURIComponent(slug)}/forfeit`,{method:'POST',body:{}})}catch{}navigate(`/retro/game/${encodeURIComponent(slug)}`,true)}else if(type==='daily-claim'){try{await api('/api/daily-bonus/claim',{method:'POST',body:{}});toast('Bônus resgatado!');render()}catch(x){toast(x.message,true)}}else if(type==='logout'){try{await api('/api/auth/logout',{method:'POST',body:{}})}catch{}clearAuth();navigate('/login',true)}});

app()?.addEventListener('input',e=>{if(e.target.id==='pf8-home-search'){clearTimeout(searchDebounce);searchDebounce=setTimeout(()=>{const q=e.target.value.trim();if(q.length>=2)navigate(`/casino/provider/all/category/all?q=${encodeURIComponent(q)}`)},450)}if(e.target.id==='pf8-casino-search'&&casinoState){clearTimeout(searchDebounce);searchDebounce=setTimeout(async()=>{casinoState.search=e.target.value.trim();try{await casinoLoad(true)}catch(x){toast(x.message,true)}},450)}});

app()?.addEventListener('submit',async e=>{e.preventDefault();const f=e.target;const btn=f.querySelector('button[type=submit]');if(btn){btn.disabled=true;btn.dataset.old=btn.innerHTML;btn.textContent='Aguarde...'}try{if(f.id==='pf8-login'){const fd=new FormData(f);const d=await api('/api/auth/login',{method:'POST',body:{email:fd.get('email'),password:fd.get('password')}});saveAuth(d);navigate('/',true)}else if(f.id==='pf8-register'){const fd=new FormData(f);const qs=new URLSearchParams(location.search);const d=await api('/api/auth/register',{method:'POST',body:{name:fd.get('name'),email:fd.get('email'),phone:fd.get('phone'),password:fd.get('password'),cupom:fd.get('cupom')||null,reference_code:qs.get('ref')||qs.get('reference_code')||null}});saveAuth(d);navigate('/',true)}else if(f.id==='pf8-forgot'){const fd=new FormData(f);const d=await api('/api/auth/forget-password',{method:'POST',body:{email:fd.get('email')}});toast(d?.message||'Se o e-mail existir, enviaremos o link.')}else if(f.id==='pf8-reset'){const fd=new FormData(f);const d=await api(`/api/auth/reset-password/${encodeURIComponent(fd.get('token'))}`,{method:'POST',body:{email:fd.get('email'),token:fd.get('token'),password:fd.get('password'),password_confirmation:fd.get('password_confirmation')}});toast(d?.message||'Senha alterada.');setTimeout(()=>navigate('/login',true),700)}else if(f.id==='pf8-deposit'){const fd=new FormData(f);const raw=String(fd.get('amount')||'').replace(/\./g,'').replace(',','.');const amount=Number(raw);const body={amount,paymentType:'pix',gateway:app().dataset.depositGateway||'',accept_bonus:false,cpf:app().dataset.cpf||fd.get('cpf')||''};const d=await api('/api/carteira_wallet/deposit/payment',{method:'POST',body});renderPixResult(d);toast('PIX gerado! Faça o pagamento.')}else if(f.id==='pf8-create-key'){const fd=new FormData(f);const account=await getAccount();await api('/api/profile/pix-keys',{method:'POST',body:{holder_name:account?.user?.name||'Titular',holder_cpf:fd.get('cpf')}});toast('Chave PIX cadastrada.');render()}else if(f.id==='pf8-withdraw'){const fd=new FormData(f);const selected=document.querySelector('input[name=pix_key_id]:checked');if(!selected)throw new Error('Selecione uma chave PIX.');const amount=Number(String(fd.get('amount')||'').replace(/\./g,'').replace(',','.'));const d=await api('/api/carteira_wallet/withdraw/request',{method:'POST',body:{type:'pix',amount,pix_key_id:Number(selected.value)}});toast(d?.message||'Saque solicitado com sucesso.');setTimeout(()=>render(),500)}else if(f.id==='pf14-retro-start'){const fd=new FormData(f);const slug=f.dataset.retroSlug||retroSlug();const bet=Number(String(fd.get('bet')||'').replace(/\./g,'').replace(',','.'));const d=await api(`/api/retro/games/${encodeURIComponent(slug)}/start`,{method:'POST',body:{bet,client_event_id:newClientEventId()}});toast('Rodada iniciada!');navigate(`/retro/play/${encodeURIComponent(slug)}`,true)}else if(f.id==='pf13-verification'){
  const fd=new FormData(f);
  fd.set('cpf',String(fd.get('cpf')||'').replace(/\D/g,''));
  const d=await api('/api/profile/verification',{method:'POST',body:fd});
  toast(d?.message||'Verificação enviada com sucesso.');
  setTimeout(()=>render(),500)
}}catch(x){toast(x.message||'Não foi possível concluir.',true)}finally{if(btn&&document.body.contains(btn)){btn.disabled=false;btn.innerHTML=btn.dataset.old||'Continuar'}}});

const push=history.pushState,rep=history.replaceState;
history.pushState=function(...a){return push.apply(this,a)};
history.replaceState=function(...a){return rep.apply(this,a)};

addEventListener('popstate',render);
addEventListener('pageshow',e=>{if(e.persisted)render()});

document.addEventListener('click',e=>{
  const btn=e.target.closest('#pf13-player-back');
  if(!btn)return;
  e.preventDefault();
  let target='/';
  try{target=sessionStorage.getItem('pf13_game_return')||'/'}catch{}
  const u=normalizeNav(target);
  location.replace(u&&u.origin===location.origin?u.pathname+u.search+u.hash:'/');
});

function boot(){render()}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
