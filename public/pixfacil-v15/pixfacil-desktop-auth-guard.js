(()=>{'use strict';
const AUTH_ROUTE=()=>/^\/(?:login|register|forget-password|forgot-password|reset-password)(?:\/|$)/i.test(location.pathname);
let loaded=false;
function cleanOldFrame(){
  const body=document.body;
  if(body)body.classList.remove('pf-desktop-owned','pf-desktop-home');
  document.getElementById('pf-desktop-sidebar')?.remove();
  document.getElementById('pf-desktop-chrome')?.remove();
  document.getElementById('pf-desktop-experience')?.remove();
}
function loadUnified(){
  if(loaded||window.innerWidth<768||!AUTH_ROUTE())return;
  loaded=true;
  document.documentElement.classList.add('pf-unified-desktop');
  document.body?.classList.add('pf-unified-desktop');
  const s=document.createElement('script');
  s.src='/pixfacil/runtime.js?v=20260905-1830';
  s.defer=true;
  s.dataset.pfUnifiedRuntime='1';
  document.head.appendChild(s);
}
function boot(){cleanOldFrame();loadUnified();addEventListener('resize',loadUnified,{passive:true})}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot,{once:true}):boot();
})();
