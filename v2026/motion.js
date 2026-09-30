/* motion.js — Tier A · Awwwards 2026 elevation for Nilov Flowers v2026
   Loaded with <script defer>. Listens for 'nf:ready' (dispatched by the main
   inline IIFE after renderAll) so it runs against fully-rendered DOM.
   Respects prefers-reduced-motion (static fallback). Vanilla, no deps. */
(function(){
'use strict';
var RM=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
var FINE=window.matchMedia('(hover:hover) and (pointer:fine)').matches;
var $=function(s,r){return (r||document).querySelector(s)};
var $$=function(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))};
var started=false;

function init(){
  if(started)return;started=true;
  if(RM)return;
  heroChars();
  heroScroll();
  atelierRing();
  flyToCart();
  cardTilt();
  marqueeVelocity();
  viewTimelineReveals();
}

/* #2 — hero title char-stagger reveal (expo.out 1.2s, stagger .025s) */
function heroChars(){
  var title=$('[data-hero-title]');if(!title)return;
  var lines=$$('.mline',title);if(!lines.length)return;
  var idx=0;
  lines.forEach(function(line){
    var span=line.firstElementChild;if(!span)return;
    var nodes=Array.prototype.slice.call(span.childNodes);
    span.innerHTML='';
    nodes.forEach(function(n){
      if(n.nodeType===3){
        var txt=n.textContent;
        for(var i=0;i<txt.length;i++){
          var ch=txt[i];
          if(/\s/.test(ch)){span.appendChild(document.createTextNode(ch));continue}
          var s=document.createElement('span');
          s.className='ch';s.style.setProperty('--i',idx);s.textContent=ch;
          span.appendChild(s);idx++;
        }
      } else if(n.nodeType===1){
        var inner=n.textContent;
        for(var i=0;i<inner.length;i++){
          var s=document.createElement('span');
          s.className='ch is-accent';s.style.setProperty('--i',idx);s.textContent=inner[i];
          span.appendChild(s);idx++;
        }
      }
    });
  });
  requestAnimationFrame(function(){requestAnimationFrame(function(){
    title.classList.add('is-revealed');
  })});
}

/* #1 — hero 3-layer parallax (bg 0.3×, mid 0.6×, fg 1.0×) + velocity blur/skew
   P0 F5b: REAL pin-scrub — hero wrapped in 2×hero-height container, position:sticky
   pins hero for one hero-height of scroll while layers parallax at 0.3/0.6/1.0× rates.
   IntersectionObserver gates rAF; prefers-reduced-motion returns early (static). */
function heroScroll(){
  var hero=$('.hero');if(!hero)return;
  var reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  if(reduced.matches)return;
  /* Real pin: wrap .hero in a 2×hero-height container so position:sticky has a scroll range */
  if(!hero.parentNode.classList||!hero.parentNode.classList.contains('hero-pin-wrap')){
    var parent=hero.parentNode;
    var wrap=document.createElement('div');
    wrap.className='hero-pin-wrap';
    parent.insertBefore(wrap,hero);
    wrap.appendChild(hero);
  }
  var wrap=hero.parentNode;
  hero.style.position='-webkit-sticky';
  hero.style.position='sticky';
  hero.style.top='0';
  function sizeWrap(){
    var h=hero.offsetHeight||innerHeight;
    wrap.style.position='relative';
    wrap.style.height=(h*2)+'px';
  }
  sizeWrap();
  if(window.ResizeObserver){new ResizeObserver(sizeWrap).observe(hero)}
  else{window.addEventListener('resize',sizeWrap)}
  var bg=$('.hero__bg'),fig=$('#heroFig'),title=$('.hero__t'),accent=$('.hero__a'),lead=$('.hero__l'),cta=$('.hero__c');
  var hcards=$$('.hcard');
  var M=innerWidth<768?0.45:1;
  var lastY=scrollY,lastT=performance.now(),vel=0,fastTo,raf;
  function tick(){
    var y=scrollY||0;
    var now=performance.now(),dt=Math.max(1,now-lastT);
    var inst=Math.abs(y-lastY)/dt*1000;
    vel=vel*0.6+inst*0.4;
    lastY=y;lastT=now;
    if(vel>1400){
      hero.classList.add('is-fast');
      var b=Math.min(4,(vel-1400)/500);
      hero.style.setProperty('--vblur',b.toFixed(2)+'px');
      hero.style.setProperty('--vskew','-1.5deg');
      clearTimeout(fastTo);fastTo=setTimeout(function(){hero.classList.remove('is-fast')},200);
    } else if(hero.classList.contains('is-fast')&&vel<700){
      hero.classList.remove('is-fast');
    }
    /* Pin progress p (0..1) across the wrap's pin range (wrap.height − hero.height) */
    var r=wrap.getBoundingClientRect();
    var pinRange=Math.max(1,r.height-(hero.offsetHeight||innerHeight));
    var p=Math.min(1,Math.max(0,-r.top/pinRange));
    /* Layer parallax: bg 0.3× (slowest, far), mid 0.6×, fg 1.0× (fastest, near).
       Ratios exact: bg=27px (0.3× of 90), mid=54px (0.6×), fg=90px (1.0×). */
    if(bg)bg.style.transform='translate3d(0,'+(p*27*M).toFixed(2)+'px,0)';
    if(fig)fig.style.transform='translate3d(0,'+(p*-54*M).toFixed(2)+'px,0) scale('+(1+p*0.05).toFixed(4)+')';
    hcards.forEach(function(c,i){c.style.transform='translate3d(0,'+(p*(i?-90:90)*M).toFixed(2)+'px,0)'});
    /* Title scale/fade as pin completes */
    if(title){var s=1-p*0.14;title.style.transform='translate3d(0,'+(-p*36).toFixed(2)+'px,0) scale('+s.toFixed(4)+')';title.style.opacity=Math.max(.3,1-p*0.7).toFixed(3)}
    if(accent)accent.style.opacity=Math.max(0,1-p*1.5).toFixed(3);
    if(lead)lead.style.opacity=Math.max(0,1-p*1.25).toFixed(3);
    if(cta)cta.style.opacity=Math.max(0,1-p*1.7).toFixed(3);
    raf=requestAnimationFrame(tick);
  }
  // gate rAF on wrap visibility (pin range)
  var io=new IntersectionObserver(function(es){es.forEach(function(e){
    if(e.isIntersecting){if(!raf)raf=requestAnimationFrame(tick)}
    else{cancelAnimationFrame(raf);raf=null}})},{threshold:0});
  io.observe(wrap);
}

/* #5 — atelier scroll-progress ring + scrub */
function atelierRing(){
  var atl=$('.atl');if(!atl)return;
  var ring=document.createElement('div');
  ring.className='atl__ring';ring.setAttribute('aria-hidden','true');
  ring.innerHTML='<svg viewBox="0 0 90 90" width="84" height="84">'+
    '<circle class="bg" cx="45" cy="45" r="42"/>'+
    '<circle class="fg" cx="45" cy="45" r="42"/>'+
    '<text class="lbl" x="45" y="49" text-anchor="middle">студия</text></svg>';
  atl.appendChild(ring);
  var fg=$('.fg',ring);
  var C=264,raf,visible=false;
  var io=new IntersectionObserver(function(es){es.forEach(function(e){
    visible=e.isIntersecting;
    ring.classList.toggle('in',e.isIntersecting);
    if(visible&&!raf)raf=requestAnimationFrame(upd);
    else if(!visible){cancelAnimationFrame(raf);raf=null}
  })},{threshold:0,rootMargin:'-20% 0px -20% 0px'});
  io.observe(atl);
  function upd(){
    if(!visible)return;
    var r=atl.getBoundingClientRect();
    var h=atl.offsetHeight||1;
    var p=Math.min(1,Math.max(0,(innerHeight*0.5-r.top)/(h-innerHeight*0.5)));
    fg.style.strokeDashoffset=(C*(1-p)).toFixed(1);
    raf=requestAnimationFrame(upd);
  }
}

/* #8 — fly-to-cart petal on add-to-cart */
function flyToCart(){
  document.addEventListener('click',function(e){
    var b=e.target.closest('[data-add]');if(!b)return;
    if(RM)return;
    var card=b.closest('.card');
    var img=card?card.querySelector('img.a'):null;
    var cart=$('#btnCart');
    if(!img||!cart)return;
    var ir=img.getBoundingClientRect(),cr=cart.getBoundingClientRect();
    var petal=document.createElement('div');
    petal.className='fly-petal';
    petal.innerHTML='<svg viewBox="0 0 24 24"><ellipse cx="12" cy="12" rx="9" ry="5.5" fill="#E7B4B8" transform="rotate(35 12 12)"/><circle cx="12" cy="12" r="2" fill="#C8A24A"/></svg>';
    petal.style.left=(ir.left+ir.width/2-8)+'px';
    petal.style.top=(ir.top+ir.height/2-8)+'px';
    document.body.appendChild(petal);
    var dx=(cr.left+cr.width/2)-(ir.left+ir.width/2);
    var dy=(cr.top+cr.height/2)-(ir.top+ir.height/2);
    var anim=petal.animate([
      {transform:'translate(0,0) scale(1) rotate(0deg)',opacity:1},
      {transform:'translate('+(dx*0.5)+'px,'+(dy*0.5-70)+'px) scale(0.85) rotate(140deg)',opacity:0.95,offset:0.5},
      {transform:'translate('+dx+'px,'+dy+'px) scale(0.35) rotate(360deg)',opacity:0}
    ],{duration:850,easing:'cubic-bezier(.2,.8,.2,1)'});
    anim.onfinish=function(){petal.remove()};
  },true);
}

/* #9/#12 — IntersectionObserver stagger reveals (replaces no-op animation-timeline: view()).
   Per-section IO reveals children with clip-path/translate/opacity choreography,
   stagger 70ms per child. Also fixes the existing [data-rv] IO: CSS clip-path
   (inset 0 0 100% 0) makes intersectionRatio=0, so the existing IO never fires.
   motion.js adds .in based on boundingClientRect intersection (bypasses clip-path). */
function viewTimelineReveals(){
  /* Section stagger reveals — per-section IO, children stagger 70ms via inline
     transitionDelay. Children get clip-path/translate/opacity choreography (not
     just opacity). Selectors verified against index.html:
       .card in #catalog (renderCatalog: <article class="card">)
       .chap in .atl     (renderAtelier: <article class="chap">)
       .jrn a in .jrn    (renderJournal: <a href="#journal">)
       .rvw in #reviews  (index.html: <div class="rvw"> marquee rows) */
  var groups=[
    {sel:'.card',root:'#catalog'},
    {sel:'.chap',root:'.atl'},
    {sel:'.jrn a',root:'.jrn'},
    {sel:'.rvw',root:'#reviews'}
  ];
  groups.forEach(function(g){
    var roots=$$(g.root);
    roots.forEach(function(root){
      var kids=Array.prototype.slice.call(root.querySelectorAll(g.sel));
      if(!kids.length)return;
      var toReveal=[];
      kids.forEach(function(k,i){
        if(k.hasAttribute('data-rv')){
          /* Tighten sibling stagger via inline transition-delay (CSS --d never set from data-d) */
          k.style.transitionDelay=(i*0.07)+'s';
        } else {
          var r=k.getBoundingClientRect();
          var inView=r.top<innerHeight&&r.bottom>0;
          if(inView)return; /* already visible — don't re-hide (avoids flash) */
          k.style.willChange='transform,opacity,clip-path';
          k.style.opacity='0';
          k.style.transform='translateY(40px)';
          k.style.clipPath='inset(0 0 100% 0)';
          k.style.transition='opacity .9s cubic-bezier(.2,.8,.2,1),transform .9s cubic-bezier(.2,.8,.2,1),clip-path 1.15s cubic-bezier(.2,.8,.2,1)';
          k.style.transitionDelay=(i*0.07)+'s';
          toReveal.push(k);
        }
      });
      if(!toReveal.length)return;
      var io=new IntersectionObserver(function(es){
        es.forEach(function(e){
          if(e.isIntersecting){
            toReveal.forEach(function(k){
              k.style.opacity='1';
              k.style.transform='none';
              k.style.clipPath='inset(0 0 0 0)';
            });
            io.disconnect();
          }
        });
      },{threshold:0.1,rootMargin:'0px 0px -6% 0px'});
      io.observe(root);
    });
  });
  /* Fix pre-existing bug: existing IO (threshold .1 + clip-path initial state) makes
     intersectionRatio=0 → .in never added → [data-rv] elements stay invisible.
     motion.js IO uses boundingClientRect intersection (ignores clip-path) and also
     sets transition-delay from data-d (CSS --d variable is never bound from the attribute). */
  var rvEls=$$('[data-rv]:not(.in)');
  if(rvEls.length){
    rvEls.forEach(function(el){
      if(el.style.transitionDelay)return; /* already set by group logic above */
      var d=el.getAttribute('data-d');
      if(d){var ms=parseFloat(d);if(!isNaN(ms))el.style.transitionDelay=(ms/1000)+'s'}
    });
    var io2=new IntersectionObserver(function(es){
      es.forEach(function(e){
        var b=e.boundingClientRect,root=e.rootBounds;
        if(root&&b.top<root.bottom&&b.bottom>root.top&&b.left<root.right&&b.right>root.left){
          e.target.classList.add('in');
          io2.unobserve(e.target);
        }
      });
    },{threshold:0,rootMargin:'0px 0px -6% 0px'});
    rvEls.forEach(function(el){io2.observe(el)});
  }
}

/* P1-15 — catalog card 3D tilt with lerp + spring-back (pointer:fine only, RM-safe) */
function cardTilt(){
  if(!FINE)return;
  $$('.card').forEach(function(card){
    var img=card.querySelector('.card__m');
    if(!img)return;
    var tx=0,ty=0,cx=0,cy=0,raf=null;
    function loop(){
      /* Lerp current → target (0.15 factor = smooth follow, no jitter) */
      cx+=(tx-cx)*0.15;
      cy+=(ty-cy)*0.15;
      img.style.transform='perspective(800px) rotateY('+cx.toFixed(2)+'deg) rotateX('+(-cy).toFixed(2)+'deg) scale(1.02)';
      if(Math.abs(tx-cx)>0.02||Math.abs(ty-cy)>0.02){
        raf=requestAnimationFrame(loop);
      } else { raf=null; }
    }
    card.addEventListener('mousemove',function(e){
      var r=card.getBoundingClientRect();
      var x=(e.clientX-r.left)/r.width-0.5;
      var y=(e.clientY-r.top)/r.height-0.5;
      tx=x*6;ty=y*6;
      if(!raf)raf=requestAnimationFrame(loop);
    });
    /* Spring-back to 0 on mouseleave (not instant clear) */
    card.addEventListener('mouseleave',function(){tx=0;ty=0;if(!raf)raf=requestAnimationFrame(loop)});
  });
}

/* P1-16 — marquee velocity: speed up on fast scroll, ease back when idle */
function marqueeVelocity(){
  var band=$('.band__t');if(!band)return;
  var lastY=scrollY||0,lastT=performance.now(),vel=0,to;
  function onScroll(){
    var y=scrollY||0,now=performance.now(),dt=Math.max(1,now-lastT);
    vel=vel*0.6+(Math.abs(y-lastY)/dt*1000)*0.4;
    lastY=y;lastT=now;
    var dur=Math.max(10,34-vel*0.018);
    band.style.animationDuration=dur.toFixed(2)+'s';
    clearTimeout(to);to=setTimeout(function(){vel=vel*0.5;band.style.animationDuration='34s'},300);
  }
  window.addEventListener('scroll',onScroll,{passive:true});
}

// Authoritative signal: 'nf:ready' is dispatched by the main IIFE after
// renderAll() completes (content.json fetched + DOM populated).
// Fallback: if it never fires within 3.5s (e.g. fetch failed), init anyway.
setTimeout(function(){if(!started)init()},3500);
window.addEventListener('nf:ready',init);
})();
