(()=>{'use strict';
const owned=()=>document.body?.classList.contains('pf15-owned-server');
const path=()=>location.pathname.replace(/\/+$/,'')||'/';
let branding=null,last='',lastWinsAt=0,winsLoading=false;

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
function html(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function setText(el,value){if(el&&value&&escSelectorText(el.textContent)!==escSelectorText(value))el.textContent=value}
function first(root,selectors){for(const s of selectors){const el=root.querySelector(s);if(el)return el}return null}
function asset(v){if(!v)return '';v=String(v);if(/^(?:https?:|data:|blob:)/i.test(v))return v;if(v.startsWith('/'))return v;v=v.replace(/^\.\//,'').replace(/^public\//,'');if(v.startsWith('storage/'))return '/'+v;if(v.startsWith('uploads/'))return '/storage/'+v;return '/storage/'+v}
function money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
function relative(v){if(!v)return 'Agora';const sec=Math.max(0,Math.floor((Date.now()-new Date(v).getTime())/1000));if(sec<60)return 'Agora';const min=Math.floor(sec/60);if(min<60)return `há ${min} min`;const h=Math.floor(min/60);return `há ${h}h`}
function updateLogo(root){
  const logo=branding?.desktop_logo||branding?.mobile_logo;if(!logo)return;
  for(const img of root.querySelectorAll('img')){
    const meta=`${img.alt||''} ${img.title||''} ${img.src||''}`.toLowerCase();
    if(/logo|central.?igaming|pixf[aá]cil/.test(meta)){
      img.src=logo;img.alt=branding.software_name||'Plataforma';
    }
  }
}
function ensureArtworkImage(container,src,href,alt,className){
  if(!container||!src)return;
  let link=container.querySelector(`:scope > .${className}`);
  if(!link){
    link=document.createElement('a');
    link.className=className;
    link.href=href;
    link.style.cssText='display:block;position:relative;overflow:hidden;min-height:0;aspect-ratio:1.875/1;padding:0;border-bottom:1px solid rgba(57,242,92,.12);text-decoration:none;background:#07120b';
    const img=document.createElement('img');
    img.alt=alt;
    img.loading='lazy';
    img.style.cssText='display:block;width:100%;height:100%;object-fit:cover;object-position:center';
    link.appendChild(img);
    container.prepend(link);
  }
  const img=link.querySelector('img');
  if(img&&img.getAttribute('src')!==src){
    img.classList.remove('pf12-img-failed');
    img.dataset.pfArtFailed='';
    img.onerror=()=>{if(img.dataset.pfArtFailed==='1')return;img.dataset.pfArtFailed='1';link.style.display='none'};
    img.onload=()=>{link.style.display='block'};
    img.src=src;
  }
}
function applyFeatureArtwork(el,src,alt){
  if(!el||!src)return;
  el.classList.add('has-art');
  el.style.cssText+=';position:relative;display:block;overflow:hidden;min-height:0;aspect-ratio:1.875/1;padding:0;background:#061009';
  let img=el.querySelector(':scope > img.pfdh-feature-art');
  if(!img){
    img=document.createElement('img');
    img.className='pfdh-feature-art';
    img.alt=alt;
    img.loading='lazy';
    img.style.cssText='position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;z-index:2;display:block';
    el.prepend(img);
  }
  for(const child of el.children){if(child!==img)child.style.visibility='hidden'}
  if(img.getAttribute('src')!==src)img.src=src;
  img.onerror=()=>{el.classList.remove('has-art');img.style.display='none';for(const child of el.children){if(child!==img)child.style.visibility='visible'}};
  img.onload=()=>{el.classList.add('has-art');img.style.display='block';for(const child of el.children){if(child!==img)child.style.visibility='hidden'}};
}
function applyHomeArtwork(){
  if(!branding||window.innerWidth<768)return;
  const content=branding.content||{};
  const heroSrc=asset(content.home_hero_image||'/pixfacil-v15/art/home-welcome.webp');
  const vipSrc=asset(content.home_vip_image||'/pixfacil-v15/art/home-vip.webp');
  const pixSrc=asset(content.home_pix_image||'/pixfacil-v15/art/home-pix.webp');
  const promoSrc=asset(content.home_promotions_image||'/pixfacil-v15/art/home-promotions.webp');

  if(path()==='/'){
    const hero=document.querySelector('#pfdh-hero');
    const heroImg=hero?.querySelector('.pfdh-hero-slide img');
    if(heroImg){
      const current=heroImg.getAttribute('src')||'';
      const isThemeFallback=current.includes('/pixfacil-v15/hero.webp')||heroImg.classList.contains('pf12-img-failed');
      if(isThemeFallback&&heroSrc&&current!==heroSrc){
        heroImg.classList.remove('pf12-img-failed');
        heroImg.src=heroSrc;
        heroImg.alt='Banner principal';
      }
    }

    applyFeatureArtwork(document.querySelector('.pfdh-feature-vip'),vipSrc,'Clube VIP');
    applyFeatureArtwork(document.querySelector('.pfdh-feature-pix'),pixSrc,'Depósito via PIX');
    ensureArtworkImage(document.querySelector('.pfdh-promos'),promoSrc,'/promocoes','Promoções','pfdh-promotions-art');
  }

  if(/^\/promocoes(?:\/|$)/i.test(path())){
    for(const card of document.querySelectorAll('.pf8-promo')){
      const img=card.querySelector(':scope > img');
      if(img&&img.classList.contains('pf12-img-failed')&&promoSrc&&img.dataset.pfAdminFallback!=='1'){
        img.dataset.pfAdminFallback='1';
        img.classList.remove('pf12-img-failed');
        img.src=promoSrc;
      }
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
  applyHomeArtwork();
}
async function hydrateLiveWins(force=false){
  if(path()!=='/'||window.innerWidth<768)return;
  const box=document.getElementById('pfdh-live');
  if(!box||winsLoading)return;
  const now=Date.now();
  if(!force&&now-lastWinsAt<15000)return;
  winsLoading=true;lastWinsAt=now;
  try{
    const r=await fetch('/api/home/live-wins',{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
    if(!r.ok)return;
    const data=await r.json(),wins=Array.isArray(data?.wins)?data.wins.slice(0,4):[];
    if(!wins.length){
      box.innerHTML='<div class="pfdh-rail-head"><b>Ganhos ao vivo</b><a href="/casino/provider/all/category/all">Ver todos →</a></div><div class="pfdh-live-empty">Aguardando novos ganhos.</div>';
      return;
    }
    box.innerHTML=`<div class="pfdh-rail-head"><b>Ganhos ao vivo</b><a href="/casino/provider/all/category/all">Ver todos →</a></div>${wins.map(w=>`<div class="pfdh-live-row"><span class="pfdh-live-thumb">${w.cover?`<img src="${html(asset(w.cover))}" alt="">`:'<b>★</b>'}</span><span><strong>${html(w.user||'Jogador')}</strong><small>ganhou <b>${html(money(w.amount))}</b><br>no ${html(w.game_name||'Jogo')}</small></span><em>${html(relative(w.created_at))}</em></div>`).join('')}`;
  }catch(_){}finally{winsLoading=false}
}
async function load(){
  try{const r=await fetch('/branding/data',{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});if(r.ok)branding=await r.json()}catch(_){}
  updatePage();
  hydrateLiveWins(true);
}
function watch(){
  const obs=new MutationObserver(()=>{updatePage();hydrateLiveWins()});
  obs.observe(document.body,{childList:true,subtree:true});
  setInterval(()=>{if(location.href!==last){last=location.href;updatePage();hydrateLiveWins(true)}else{applyHomeArtwork();hydrateLiveWins()}},1000);
  addEventListener('popstate',()=>{updatePage();hydrateLiveWins(true)});
}
function boot(){if(!owned())return;last=location.href;load().then(watch)}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot):boot();
})();
