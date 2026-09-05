(()=>{'use strict';
const DESKTOP=()=>window.innerWidth>=768;
const blocked=()=>/^\/admin(?:\/|$)/i.test(location.pathname)||/^\/games\/play(?:\/|$)/i.test(location.pathname)||/^\/sport(?:book)?\/play(?:\/|$)/i.test(location.pathname);
const home=()=>location.pathname==='/'||location.pathname==='';
let branding=null,banners=[],heroTimer=null,lastPath='';

function asset(v){if(!v)return '';v=String(v);if(/^(?:https?:|data:|blob:)/i.test(v))return v;if(v.startsWith('/'))return v;if(v.startsWith('storage/'))return '/'+v;if(v.startsWith('uploads/'))return '/storage/'+v;return '/storage/'+v}
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#039;'}[m]))}
function currentNav(href){const p=location.pathname;if(href==='/')return p==='/';return p.startsWith(href)}

async function loadConfig(){
  const [b,s]=await Promise.all([
    fetch('/branding/data',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():{}).catch(()=>({})),
    fetch('/api/settings/banners/',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():{}).catch(()=>({}))
  ]);
  branding=b||{};
  banners=Array.isArray(s?.desktop)?s.desktop:(Array.isArray(s?.banners)?s.banners.filter(x=>x.show_desktop!==false):[]);
}

function chrome(){
  const logo=asset(branding?.desktop_logo)||'/pixfacil-v15/logo.svg';
  const name=esc(branding?.software_name||'PixFácil');
  const links=[['/','Início'],['/casino/provider/all/category/all','Jogos'],['/retro','Arcade'],['/bonus','Bônus'],['/vip','VIP']];
  return `<div class="pfd-top"><a class="pfd-brand" href="/" aria-label="${name}"><img src="${esc(logo)}" alt="${name}"></a><nav class="pfd-nav">${links.map(([h,l])=>`<a href="${h}" class="${currentNav(h)?'active':''}">${l}</a>`).join('')}</nav><div class="pfd-actions"><a class="ghost" href="/profile/account">Minha conta</a><a class="primary" href="/profile/deposit">Depositar</a></div></div>`;
}

function mountChrome(){
  if(!DESKTOP()||blocked()){unmount();return}
  document.body.classList.add('pf-desktop-owned');
  let el=document.getElementById('pf-desktop-chrome');
  if(!el){el=document.createElement('header');el.id='pf-desktop-chrome';document.body.prepend(el)}
  el.innerHTML=chrome();
  markLegacyHeader();
  mountHero();
}

function markLegacyHeader(){
  const root=document.getElementById('ondagamesv1');if(!root)return;
  const candidate=root.querySelector('.navtop-color, header');
  if(candidate&&candidate.parentElement===root)candidate.setAttribute('data-desktop-legacy-header','1');
}

function heroMarkup(){
  const list=banners.filter(x=>x&&x.image&&x.show_desktop!==false&&x.is_active!==false);
  if(!list.length){return `<div class="pfd-hero"><div class="pfd-hero-empty"><div><strong>${esc(branding?.software_name||'PixFácil')}</strong><span>Cadastre um banner em Admin → Tema e Aparência → Banners da Plataforma.</span></div></div></div>`}
  return `<div class="pfd-hero">${list.map((b,i)=>`<div class="pfd-slide ${i===0?'active':''}" data-pfd-slide="${i}"><img src="${esc(asset(b.image))}" alt="${esc(b.description||'Banner')}">${b.link?`<a href="${esc(b.link)}" aria-label="${esc(b.description||'Abrir banner')}"></a>`:''}</div>`).join('')}<div class="pfd-dots">${list.map((_,i)=>`<button type="button" class="pfd-dot ${i===0?'active':''}" data-pfd-dot="${i}" aria-label="Banner ${i+1}"></button>`).join('')}</div></div>`;
}

function mountHero(){
  clearInterval(heroTimer);heroTimer=null;
  document.body.classList.remove('pf-desktop-has-hero');
  document.getElementById('pf-desktop-hero-mount')?.remove();
  if(!home()||!DESKTOP()||blocked())return;
  const root=document.getElementById('ondagamesv1');if(!root)return;
  const mount=document.createElement('section');mount.id='pf-desktop-hero-mount';mount.innerHTML=heroMarkup();
  root.before(mount);
  document.body.classList.add('pf-desktop-has-hero');
  const slides=[...mount.querySelectorAll('[data-pfd-slide]')],dots=[...mount.querySelectorAll('[data-pfd-dot]')];
  if(slides.length<2)return;
  let idx=0;
  const show=n=>{idx=(n+slides.length)%slides.length;slides.forEach((s,i)=>s.classList.toggle('active',i===idx));dots.forEach((d,i)=>d.classList.toggle('active',i===idx))};
  const restart=()=>{clearInterval(heroTimer);heroTimer=setInterval(()=>show(idx+1),6500)};
  dots.forEach((d,i)=>d.addEventListener('click',()=>{show(i);restart()}));
  restart();
}

function unmount(){
  document.body.classList.remove('pf-desktop-owned','pf-desktop-has-hero');
  document.getElementById('pf-desktop-chrome')?.remove();
  document.getElementById('pf-desktop-hero-mount')?.remove();
  clearInterval(heroTimer);heroTimer=null;
}

function watchRoute(){
  const tick=()=>{
    if(location.pathname!==lastPath){lastPath=location.pathname;mountChrome()}
    else if(DESKTOP()&&!blocked()){markLegacyHeader()}
    else if(!DESKTOP()){unmount()}
  };
  setInterval(tick,450);
  addEventListener('popstate',tick);
  addEventListener('resize',tick);
}

async function boot(){
  if(!DESKTOP()||blocked())return;
  await loadConfig();
  lastPath=location.pathname;
  mountChrome();
  watchRoute();
  const observer=new MutationObserver(()=>{if(DESKTOP()&&!blocked()){markLegacyHeader();if(home()&&!document.getElementById('pf-desktop-hero-mount'))mountHero()}});
  const root=document.getElementById('ondagamesv1');if(root)observer.observe(root,{childList:true,subtree:true});
}

document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot):boot();
})();
