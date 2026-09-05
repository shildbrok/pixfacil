(function(){
  'use strict';
  var params=new URLSearchParams(location.search),slug=(params.get('slug')||'').toLowerCase();
  var names={
    sub:['Subway Money','PIXFÁCIL EDITION'],fruit:['Fruit Cash','NEON SLICE'],dino:['DinoWin Turbo','PIXFÁCIL ORIGINAL'],
    angry:['Angry Cash','NEON SIEGE'],candy:['Candy Cash','PIXEL RUSH'],jetpack:['Jetpack Cash','HYPERFLIGHT'],
    pacman:['Pacman Cash','NEON EDITION'],helix:['Helix Cash','NEON DROP'],blockwin:['Block Win','MATRIX EDITION']
  };
  var info=names[slug]||['PixFácil Original','ARCADE EDITION'];
  document.documentElement.setAttribute('data-pf-original',slug||'generic');
  function mount(){
    if(document.getElementById('pf-original-intro'))return;
    var intro=document.createElement('div');intro.id='pf-original-intro';
    intro.innerHTML='<div class="pfo-box"><span class="pfo-brand">PIXFÁCIL ORIGINALS</span><strong class="pfo-name"></strong><span class="pfo-edition"></span><i class="pfo-line"></i></div>';
    intro.querySelector('.pfo-name').textContent=info[0];intro.querySelector('.pfo-edition').textContent=info[1];
    var scan=document.createElement('div');scan.id='pf-original-scan';
    var badge=document.createElement('div');badge.id='pf-original-badge';badge.innerHTML='<i></i><span>PIXFÁCIL ORIGINAL</span>';
    document.body.appendChild(scan);document.body.appendChild(badge);document.body.appendChild(intro);
    try{parent.postMessage({type:'pf-original-event',event:'open',slug:slug},location.origin)}catch(e){}
    setTimeout(function(){intro.remove()},1500);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
  window.PixFacilOriginals={event:function(name,payload){try{parent.postMessage(Object.assign({type:'pf-original-event',event:name,slug:slug},payload||{}),location.origin)}catch(e){}}};
}());
