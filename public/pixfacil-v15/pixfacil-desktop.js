(()=>{'use strict';
const CFG=window.PIXFACIL_MOBILE_CONFIG||{};
const DESKTOP=()=>window.innerWidth>=768;
const PATH=()=>location.pathname.replace(/\/+$/,'')||'/';
const AUTH=()=>/^\/(?:login|register|forget-password|forgot-password|reset-password)(?:\/|$)/i.test(PATH());
const BLOCKED=()=>/^\/admin(?:\/|$)/i.test(PATH())||/^\/games\/play(?:\/|$)/i.test(PATH())||/^\/sport(?:book)?\/play(?:\/|$)/i.test(PATH());
let runtimeLoaded=false,lastHref=location.href;

const I={home:'<path d="M3 11.5 12 4l9 7.5V21h-6v-6H9v6H3z"/>',game:'<path d="M7 9h10l3 3v5a3 3 0 0 1-3 3l-3-3h-4l-3 3a3 3 0 0 1-3-3v-5z"/><path d="M8 12v4M6 14h4"/>',live:'<circle cx="12" cy="12" r="2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4"/>',crown:'<path d="m3 7 4 4 5-7 5 7 4-4-2 11H5zM5 21h14"/>',gift:'<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13"/>',user:'<circle cx="12" cy="8" r="4"/><path d="M4 21c.7-5 3.2-7 8-7s7.3 2 8 7"/>',history:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8"/>',support:'<path d="M4 13a8 8 0 1 1 16 0v5a2 2 0 0 1-2 2h-3v-6h5M4 14h4v6H6a2 2 0 0 1-2-2z"/>',search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',deposit:'<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',withdraw:'<path d="M12 21V9M7 14l5-5 5 5M4 3h16"/>',shield:'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',star:'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>'};
function ico(n){return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${I[n]||I.star}</svg>`}
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function active(h){const p=PATH();return h==='/'?p==='/':p.startsWith(h.split('?')[0])}
function navItem(icon,href,label){return `<a class="pfd-nav-item${active(href)?' active':''}" href="${href}">${ico(icon)}<span>${esc(label)}</span></a>`}
function shellMarkup(){
  const logo=CFG.pixfacilLogo||'/pixfacil-v15/logo.svg';
  return `<aside id="pfd-sidebar"><a class="pfd-brand" href="/"><img src="${esc(logo)}" alt="${esc(CFG.softwareName||'Plataforma')}"></a><nav class="pfd-nav">${navItem('home','/','Início')}${navItem('game','/casino/provider/all/category/all','Jogos')}${navItem('live','/casino/provider/all/category/live','Ao vivo')}${navItem('crown','/retro','Originais')}${navItem('gift','/bonus','Bônus')}${navItem('star','/vip','VIP')}<div class="pfd-nav-sep"></div>${navItem('user','/profile/account','Minha conta')}${navItem('history','/profile/transactions','Transações')}${navItem('users','/profile/affiliate','Afiliados')}${navItem('support','/support-center','Suporte')}</nav><a class="pfd-responsible" href="/profile/responsible-gaming">${ico('shield')}<span><b>18+ Jogo responsável</b><small>Controle e consciência</small></span></a></aside><header id="pfd-header"><label class="pfd-search">${ico('search')}<input id="pfd-search-input" placeholder="Buscar jogos, provedores..." autocomplete="off"><kbd>Ctrl K</kbd></label><div class="pfd-head-actions"><a class="pfd-icon" href="/bonus" aria-label="Bônus">${ico('bell')}<i></i></a><div id="pfd-account-slot"></div><a class="pfd-deposit" href="/profile/deposit">${ico('deposit')}<span>Depositar</span></a><a class="pfd-withdraw" href="/profile/withdraw">${ico('withdraw')}<span>Sacar</span></a></div></header>`;
}
function unmountShell(){
  document.getElementById('pfd-sidebar')?.remove();
  document.getElementById('pfd-header')?.remove();
  document.body?.classList.remove('pfd-shell-active');
}
function mountShell(){
  if(!DESKTOP()||AUTH()||BLOCKED()){unmountShell();return}
  document.documentElement.classList.add('pf-unified-desktop');
  document.body?.classList.add('pf-unified-desktop','pfd-shell-active');
  if(!document.getElementById('pfd-sidebar'))document.body.insertAdjacentHTML('afterbegin',shellMarkup());
  bindShell();loadAccount();
}
function bindShell(){
  const q=document.getElementById('pfd-search-input');if(q&&!q.dataset.bound){q.dataset.bound='1';q.addEventListener('keydown',e=>{if(e.key==='Enter'&&q.value.trim())location.href='/casino/provider/all/category/all?q='+encodeURIComponent(q.value.trim())})}
}
function money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
async function loadAccount(){
  const slot=document.getElementById('pfd-account-slot');if(!slot)return;
  const token=localStorage.getItem('token');if(!token){slot.innerHTML='<a class="pfd-login" href="/login">Entrar</a>';return}
  try{const h={Accept:'application/json','X-Requested-With':'XMLHttpRequest',Authorization:'Bearer '+token};const st=localStorage.getItem('session_token');if(st)h['X-Session-Token']=st;const r=await fetch('/api/profile/account',{credentials:'same-origin',headers:h});if(!r.ok)throw new Error();const a=await r.json(),w=a.wallet||{};const total=Number.isFinite(Number(w.total_balance))?Number(w.total_balance):Number(w.balance||0)+Number(w.balance_withdrawal||0)+Number(w.balance_bonus||0);slot.innerHTML=`<a class="pfd-account" href="/profile/account"><span>Saldo</span><b>${money(total)}</b></a>`}catch{slot.innerHTML='<a class="pfd-login" href="/login">Entrar</a>'}
}
function loadRuntime(){
  if(runtimeLoaded||!DESKTOP()||BLOCKED())return;
  runtimeLoaded=true;
  const s=document.createElement('script');
  const version=encodeURIComponent(CFG.assetVersion||'20260905-1907');
  s.src='/pixfacil/runtime.js?v='+version;
  s.defer=true;
  s.dataset.pfUnifiedRuntime='1';
  document.head.appendChild(s);
}
function routeTick(){
  if(location.href!==lastHref){lastHref=location.href;mountShell();loadRuntime()}
}
function boot(){
  mountShell();loadRuntime();
  document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'&&!AUTH()&&!BLOCKED()){e.preventDefault();document.getElementById('pfd-search-input')?.focus()}});
  addEventListener('popstate',()=>{mountShell();loadRuntime()});
  addEventListener('resize',()=>{mountShell();loadRuntime()},{passive:true});
  setInterval(routeTick,180);
}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot,{once:true}):boot();
})();
