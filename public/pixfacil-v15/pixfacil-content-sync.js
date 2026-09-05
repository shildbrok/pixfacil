(()=>{'use strict';
const owned=()=>document.body?.classList.contains('pf15-owned-server');
const path=()=>location.pathname.replace(/\/+$/,'')||'/';
let branding=null,last='';

const routeMap=[
  [/^\/login$/i,'login'],
  [/^\/register$/i,'register'],
  [/^\/(?:forget-password|forgot-password|reset-password)/i,'forgot'],
  [/^\/profile\/account/i,'profile'],
  [/^\/profile\/deposit/i,'deposit'],
  [/^\/profile\/withdraw/i,'withdraw'],
  [/^\/profile\/transactions/i,'transactions'],
  [/^\/profile\/bets/i,'bets'],
  [/^\/profile\/(?:verification|identity)/i,'kyc'],
  [/^\/profile\/affiliate/i,'affiliate'],
  [/^\/support-center/i,'support'],
  [/^\/profile\/responsible-gaming/i,'responsible'],
  [/^\/bonus/i,'bonus'],
  [/^\/vip/i,'vip'],
  [/^\/missions/i,'missions'],
];
function key(){const p=path();for(const [rx,k] of routeMap)if(rx.test(p))return k;return ''}
function escSelectorText(v){return String(v||'').trim()}
function setText(el,value){if(el&&value&&escSelectorText(el.textContent)!==escSelectorText(value))el.textContent=value}
function first(root,selectors){for(const s of selectors){const el=root.querySelector(s);if(el)return el}return null}
function updateLogo(root){
  const logo=branding?.desktop_logo||branding?.mobile_logo;if(!logo)return;
  for(const img of root.querySelectorAll('img')){
    const meta=`${img.alt||''} ${img.title||''} ${img.src||''}`.toLowerCase();
    if(/logo|central.?igaming|pixf[aá]cil/.test(meta)){
      img.src=logo;img.alt=branding.software_name||'Plataforma';
    }
  }
}
function updatePage(){
  if(!owned()||!branding)return;
  const k=key(),content=branding.content||{},body=document.body;
  [...body.classList].filter(x=>x.startsWith('pf-content-page-')).forEach(x=>body.classList.remove(x));
  if(k)body.classList.add(`pf-content-page-${k}`);
  body.dataset.pfContentPage=k||'generic';
  document.documentElement.style.setProperty('--pf-admin-brand-name',`"${String(branding.software_name||'').replace(/"/g,'')}"`);

  const roots=[document.getElementById('pixfacil-v15-app'),document.getElementById('ondagamesv1')].filter(Boolean);
  for(const root of roots){
    updateLogo(root);
    if(!k)continue;
    const title=content[`${k}_title`],sub=content[`${k}_subtitle`],badge=content[`${k}_badge`],help=content[`${k}_help`];
    const titleEl=first(root,['[data-pf-admin-title]','.pf8-title h1','.pf8-auth-card h1','.pf8-auth-card h2','.pf8-pagehead h1','main h1','main h2']);
    const subEl=first(root,['[data-pf-admin-subtitle]','.pf8-title p','.pf8-auth-card .pf8-auth-sub','.pf8-pagehead p','main h1 + p','main h2 + p']);
    const badgeEl=first(root,['[data-pf-admin-badge]','.pf8-auth-badge','.pf8-auth-card .badge']);
    const helpEl=first(root,['[data-pf-admin-help]','.pf8-help','.pf8-form-help']);
    setText(titleEl,title);setText(subEl,sub);setText(badgeEl,badge);setText(helpEl,help);
  }

  for(const el of document.querySelectorAll('[data-pf-software-name]'))setText(el,branding.software_name);
  for(const el of document.querySelectorAll('[data-pf-brand-tagline]'))setText(el,content.brand_tagline);
  for(const el of document.querySelectorAll('[data-pf-footer-text]'))setText(el,content.footer_text);
}
async function load(){
  try{const r=await fetch('/branding/data',{credentials:'same-origin',headers:{Accept:'application/json'}});if(r.ok)branding=await r.json()}catch(_){}
  updatePage();
}
function watch(){
  const obs=new MutationObserver(()=>updatePage());
  obs.observe(document.body,{childList:true,subtree:true});
  setInterval(()=>{if(location.href!==last){last=location.href;updatePage()}},350);
  addEventListener('popstate',updatePage);
}
function boot(){if(!owned())return;last=location.href;load().then(watch)}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot):boot();
})();
