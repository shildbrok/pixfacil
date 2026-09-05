(()=>{'use strict';
const DESKTOP=()=>window.innerWidth>=768;
const BLOCKED=()=>/^\/admin(?:\/|$)/i.test(location.pathname)||/^\/games\/play(?:\/|$)/i.test(location.pathname)||/^\/sport(?:book)?\/play(?:\/|$)/i.test(location.pathname);
let loaded=false;
function load(){
  if(loaded||!DESKTOP()||BLOCKED())return;
  loaded=true;
  document.documentElement.classList.add('pf-unified-desktop');
  document.body?.classList.add('pf-unified-desktop');
  const s=document.createElement('script');
  s.src='/pixfacil/runtime.js?v=20260905-1830';
  s.defer=true;
  s.dataset.pfUnifiedRuntime='1';
  document.head.appendChild(s);
}
function boot(){load();addEventListener('resize',load,{passive:true})}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot,{once:true}):boot();
})();
