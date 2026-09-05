(()=>{'use strict';
const AUTH_ROUTE=()=>/^\/(?:login|register|forget-password|forgot-password|reset-password)(?:\/|$)/i.test(location.pathname);
function cleanDesktopFrame(){
  if(!AUTH_ROUTE())return;
  const body=document.body;
  if(body)body.classList.remove('pf-desktop-owned','pf-desktop-home');
  document.getElementById('pf-desktop-sidebar')?.remove();
  document.getElementById('pf-desktop-chrome')?.remove();
  document.getElementById('pf-desktop-experience')?.remove();
}
function boot(){
  cleanDesktopFrame();
  setInterval(cleanDesktopFrame,150);
  addEventListener('popstate',cleanDesktopFrame);
}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot):boot();
})();
