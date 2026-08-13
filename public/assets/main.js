/* ============================================================
   PATRIOT PEST CONTROL - shared behaviour
   Robust & page-agnostic: each feature self-gates on its element.
   ============================================================ */
(function(){
"use strict";
var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

/* ---------- mobile nav ---------- */
var menuBtn = document.getElementById("menu-btn");
var navlinks = document.querySelector(".navlinks");
if (menuBtn && navlinks){
  menuBtn.addEventListener("click", function(){
    var open = navlinks.classList.toggle("open");
    menuBtn.textContent = open ? "× Close" : "☰ Menu";
  });
  navlinks.querySelectorAll("a").forEach(function(a){
    a.addEventListener("click", function(){ navlinks.classList.remove("open"); menuBtn.textContent="☰ Menu"; });
  });
}

/* ---------- bug field canvas ---------- */
var cv = document.getElementById("bugfield");
if (cv){
  var cx = cv.getContext("2d");
  var W,H,DPR = Math.min(window.devicePixelRatio||1,2);
  var mouse = {x:-9999,y:-9999};
  var bugs = [], splats = [];
  function resize(){
    W = window.innerWidth; H = window.innerHeight;
    cv.width = W*DPR; cv.height = H*DPR;
    cv.style.width = W+"px"; cv.style.height = H+"px";
    cx.setTransform(DPR,0,0,DPR,0,0);
  }
  resize(); window.addEventListener("resize", resize);
  for (var i=0;i<56;i++){
    bugs.push({x:Math.random()*innerWidth,y:Math.random()*innerHeight,vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,a:Math.random()*6.28,s:2.2+Math.random()*2.4,wig:Math.random()*6.28});
  }
  window.addEventListener("mousemove", function(e){ mouse.x=e.clientX; mouse.y=e.clientY; });
  window.addEventListener("click", function(e){ splats.push({x:e.clientX,y:e.clientY,t:1,r:6+Math.random()*5}); });
  function drawBug(b){
    cx.save(); cx.translate(b.x,b.y); cx.rotate(b.a);
    cx.fillStyle="rgba(200,185,140,0.32)";
    cx.beginPath(); cx.ellipse(0,0,b.s*1.5,b.s*.8,0,0,6.28); cx.fill();
    cx.beginPath(); cx.arc(b.s*1.6,0,b.s*.55,0,6.28); cx.fill();
    cx.strokeStyle="rgba(200,185,140,0.2)"; cx.lineWidth=1;
    for(var l=-1;l<=1;l++){ cx.beginPath(); cx.moveTo(l*b.s*.7,0); cx.lineTo(l*b.s*.7-b.s*.6,(l||1)*b.s); cx.stroke(); }
    cx.restore();
  }
  function loop(){
    cx.clearRect(0,0,W,H);
    for(var i=0;i<bugs.length;i++){
      var b=bugs[i]; b.wig+=.12;
      b.vx+=(Math.random()-.5)*.06+Math.sin(b.wig)*.01;
      b.vy+=(Math.random()-.5)*.06+Math.cos(b.wig)*.01;
      var dx=b.x-mouse.x, dy=b.y-mouse.y, d2=dx*dx+dy*dy;
      if(d2<16900&&d2>1){ var d=Math.sqrt(d2), f=(130-d)/130*.9; b.vx+=dx/d*f; b.vy+=dy/d*f; }
      b.vx*=.94; b.vy*=.94; b.x+=b.vx; b.y+=b.vy;
      if(Math.abs(b.vx)+Math.abs(b.vy)>.08) b.a=Math.atan2(b.vy,b.vx);
      if(b.x<-20)b.x=W+20; if(b.x>W+20)b.x=-20; if(b.y<-20)b.y=H+20; if(b.y>H+20)b.y=-20;
      drawBug(b);
    }
    for(var j=splats.length-1;j>=0;j--){
      var s=splats[j]; s.t-=.012;
      if(s.t<=0){ splats.splice(j,1); continue; }
      cx.fillStyle="rgba(200,64,42,"+(s.t*.5)+")";
      cx.beginPath(); cx.arc(s.x,s.y,s.r*(2-s.t),0,6.28); cx.fill();
      cx.beginPath(); cx.arc(s.x+s.r*.9,s.y-s.r*.5,s.r*.4*(2-s.t),0,6.28); cx.fill();
      cx.beginPath(); cx.arc(s.x-s.r*.7,s.y+s.r*.6,s.r*.3*(2-s.t),0,6.28); cx.fill();
    }
    if(!reduced) requestAnimationFrame(loop);
  }
  loop();
}

/* ---------- crosshair HUD (desktop, page-wide) ---------- */
var xhv=document.getElementById("xh-v"), xhh=document.getElementById("xh-h"), xhr=document.getElementById("xh-ring");
if (xhv && xhh && xhr && window.matchMedia("(pointer: fine)").matches){
  ["mousemove","mousedown","mouseup"].forEach(function(ev){
    window.addEventListener(ev, function(e){
      xhv.style.left=e.clientX+"px";
      xhh.style.top=e.clientY+"px";
      xhr.style.left=e.clientX+"px";
      xhr.style.top=e.clientY+"px";
    });
  });
  document.documentElement.classList.add("has-crosshair");
}

/* ---------- HUD clock ---------- */
var clock = document.getElementById("hud-clock");
if (clock){
  var tickClock = function(){
    var d=new Date();
    clock.textContent = String(d.getHours()).padStart(2,"0")+":"+String(d.getMinutes()).padStart(2,"0")+":"+String(d.getSeconds()).padStart(2,"0")+" LOCAL";
  };
  tickClock(); setInterval(tickClock,1000);
}

/* ---------- typewriter mission brief ---------- */
var briefEl = document.getElementById("brief-lines");
if (briefEl){
  var BRIEF = briefEl.dataset.lines ? JSON.parse(briefEl.dataset.lines) : [
    "OPERATION ......... PEST-FREE HOME",
    "COMMANDER ......... VET. SKYLER ROSE",
    "THEATER ........... WA / ID / OR / AZ",
    "RESPONSE .......... SAME-DAY AVAILABLE",
    "WARRANTY .......... 90 DAYS, FULL",
    "COLLATERAL ........ FAMILY & PET SAFE",
    "STATUS ............ >> GO FOR LAUNCH"
  ];
  if (reduced){
    briefEl.innerHTML = BRIEF.map(function(l){return '<div class="line">'+l+'</div>';}).join("");
  } else {
    var li=0, ci=0, cur=null;
    function typeStep(){
      if(li>=BRIEF.length){ var c=briefEl.querySelector(".caret"); if(c)c.remove(); return; }
      if(ci===0){ cur=document.createElement("div"); cur.className="line"; briefEl.appendChild(cur); }
      ci+=2; cur.textContent = BRIEF[li].slice(0,ci);
      if(ci>=BRIEF[li].length){ li++; ci=0; setTimeout(typeStep,240); } else setTimeout(typeStep,14);
    }
    briefEl.innerHTML='<span class="caret"></span>';
    setTimeout(typeStep,600);
  }
}

/* ---------- ticker ---------- */
var tick = document.getElementById("ticker-track");
if (tick && !tick.children.length){
  var CITIES = ["Spokane","Spokane Valley","Cheney","Liberty Lake","Airway Heights","Medical Lake","Deer Park","Mead","Coeur d'Alene","Post Falls","Hayden","Rathdrum","Hermiston","Milton-Freewater","Phoenix"];
  var seq = CITIES.map(function(c){return "<span><i>▸</i>"+c.toUpperCase()+" - SAME-DAY AVAILABLE</span>";}).join("");
  tick.innerHTML = seq + seq;
}

/* ---------- FAQ accordion ---------- */
document.querySelectorAll(".faq-q").forEach(function(btn){
  btn.addEventListener("click", function(){
    var item = btn.closest(".faq-item");
    var ans = item.querySelector(".faq-a");
    var open = item.classList.toggle("open");
    ans.style.maxHeight = open ? ans.scrollHeight+"px" : "0";
  });
});

/* ---------- forms (demo submit) ---------- */
document.querySelectorAll("form[data-demo]").forEach(function(form){
  form.addEventListener("submit", function(e){
    e.preventDefault();
    var ok = form.querySelector(".form-success");
    if (ok){ ok.classList.add("show"); ok.scrollIntoView({behavior: reduced?"auto":"smooth", block:"center"}); }
    form.reset();
    // Fire GA4 conversion event after successful demo submission
    if (window.gtag) gtag('event', 'generate_lead', { 'event_category': 'engagement', 'event_label': 'Contact Form' });
  });
});

/* ---------- GSAP ---------- */
if (window.gsap && window.ScrollTrigger){
  gsap.registerPlugin(ScrollTrigger);
  document.documentElement.classList.add('gsap-ok');

  if (!reduced && window.Lenis && window.matchMedia("(min-width: 900px) and (pointer: fine)").matches){
    var lenis = new Lenis({duration:1.15, smoothWheel:true});
    window.__lenis = lenis;
    lenis.on("scroll", ScrollTrigger.update);
    gsap.ticker.add(function(t){ lenis.raf(t*1000); });
    gsap.ticker.lagSmoothing(0);
  }

  /* progress bar */
  var prog = document.getElementById("progress");
  if (prog){
    gsap.to(prog,{scaleX:1,ease:"none",scrollTrigger:{trigger:document.body,start:"top top",end:"bottom bottom",scrub:.3}});
  }

  /* reveals */
  gsap.utils.toArray("[data-reveal]").forEach(function(el){
    gsap.to(el,{opacity:1,y:0,duration:.9,ease:"power3.out",scrollTrigger:{trigger:el,start:"top 90%",once:true}});
  });

  /* counters */
  gsap.utils.toArray("[data-count]").forEach(function(el){
    var target = parseInt(el.dataset.count,10);
    ScrollTrigger.create({trigger:el,start:"top 88%",once:true,onEnter:function(){
      gsap.to({v:0},{v:target,duration:1.6,ease:"power2.out",onUpdate:function(){ el.textContent=Math.round(this.targets()[0].v); }});
    }});
  });

  /* threat meters (home) */
  var threats = document.getElementById("threats");
  if (threats){
    ScrollTrigger.create({trigger:threats,start:"top 70%",once:true,onEnter:function(){
      document.querySelectorAll(".meter .fill").forEach(function(f,i){
        setTimeout(function(){ f.style.width=f.dataset.lvl+"%"; },200+i*90);
      });
    }});
  }

  /* hero strike line */
  var strike = document.querySelector("#hero h1 .strike");
  if (strike){
    gsap.fromTo(strike,{opacity:0,x:-30},{opacity:1,x:0,duration:.8,delay:.3,ease:"power3.out"});
    ScrollTrigger.create({trigger:strike,start:"top 85%",once:true,onEnter:function(){
      var st=document.createElement("style");
      st.textContent="#hero h1 .strike::after{transform:scaleX(1)!important;transition:transform .5s .5s cubic-bezier(.2,.8,.2,1)}";
      document.head.appendChild(st);
    }});
  }

  ScrollTrigger.refresh();
} else {
  /* ---------- no-gsap fallback: content must NEVER stay hidden ----------
     If gsap or ScrollTrigger failed to load (CDN down, offline, blocked),
     reveal everything immediately so the page is never blank. */
  document.querySelectorAll("[data-reveal]").forEach(function (el) {
    el.style.opacity = "1";
    el.style.transform = "none";
  });
  document.querySelectorAll(".meter .fill").forEach(function (f) {
    if (f.dataset.lvl) f.style.width = f.dataset.lvl + "%";
  });
}
})();
