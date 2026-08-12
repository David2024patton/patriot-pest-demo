/* ============================================================
   BEACON.JS - first-party retention beacon + THE BEACON easter egg
   ORDER 1 + ORDER 3. Loaded when the track_enabled site setting is on.
   Self-gating: the egg module only runs when #egg exists (egg_enabled
   renders it); the tracking module runs on both marketing and app shells.
   Fire rules per Nash RETENTION_EVENT_CONTRACT.md:
     - page_view on every load
     - link_click on outbound / tel / anchor clicks
     - easter_egg_click per hit, easter_egg_reveal on jackpot
     - session_end via sendBeacon on pagehide
   All strings Hamilton locked, zero em dashes.
   ============================================================ */
(function(){
"use strict";

/* ---------- identity (contract) ---------- */
function uuid(){
  var b = new Uint8Array(16);
  (window.crypto && crypto.getRandomValues) ? crypto.getRandomValues(b) : (function(){ for(var i=0;i<16;i++) b[i]=Math.floor(Math.random()*256); })();
  b[6]=(b[6]&0x0f)|0x40; b[8]=(b[8]&0x3f)|0x80;
  function h(x){ return ("0"+x.toString(16)).slice(-2); }
  return h(b[0])+h(b[1])+h(b[2])+h(b[3])+"-"+h(b[4])+h(b[5])+"-"+h(b[6])+h(b[7])+"-"+h(b[8])+h(b[9])+"-"+h(b[10])+h(b[11])+h(b[12])+h(b[13])+h(b[14])+h(b[15]);
}
function getVisitor(){
  var v;
  try { v = localStorage.getItem("pp_visitor_id"); } catch(e){}
  if(!v || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v)){
    v = uuid();
    try { localStorage.setItem("pp_visitor_id", v); } catch(e){}
  }
  return v;
}
var SESSION_TTL = 30*60*1000; // 30 minutes inactivity ends a session
function getSession(){
  var s, ts;
  try { s = sessionStorage.getItem("pp_session_id"); ts = parseInt(sessionStorage.getItem("pp_session_ts")||"0",10); } catch(e){}
  var now = Date.now();
  if(!s || !ts || (now - ts) > SESSION_TTL || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(s)){
    s = uuid(); ts = now;
    try { sessionStorage.setItem("pp_session_id", s); sessionStorage.setItem("pp_session_ts", String(ts)); } catch(e){}
  } else {
    try { sessionStorage.setItem("pp_session_ts", String(now)); } catch(e){}
  }
  return s;
}
function nowIso(){ return new Date().toISOString(); }
function pagePath(){ var p = location.pathname || "/"; return p.length>255 ? p.slice(0,255) : p; }

/* ---------- send (contract: same-origin JSON, server answers 204) ---------- */
function fire(kind, payload){
  try {
    if (navigator.sendBeacon) {
      navigator.sendBeacon("/api/track/"+kind, new Blob([JSON.stringify(payload)], {type:"application/json"}));
    } else if (window.fetch) {
      fetch("/api/track/"+kind, {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(payload), keepalive:true});
    }
  } catch(e){}
}
function trackEvent(name, extra){
  var p = { visitor_id: getVisitor(), session_id: getSession(), event_name: name, page_path: pagePath(), ts: nowIso() };
  if (extra) p.payload = extra;
  fire("event", p);
}

/* ---------- page_view ---------- */
function fireView(){
  fire("view", {
    visitor_id: getVisitor(),
    session_id: getSession(),
    page_path: pagePath(),
    page_title: document.title || "",
    referrer: document.referrer || "",
    ts: nowIso()
  });
}

/* ---------- link_click (outbound + tel + in-page anchors) ---------- */
function wireLinks(){
  var links = document.querySelectorAll('a[href^="http"], a[href^="tel:"], a[href^="#"]');
  for (var i=0;i<links.length;i++){
    (function(a){
      a.addEventListener("click", function(){
        trackEvent("link_click", { target: (a.getAttribute("href")||"").slice(0,255) });
      });
    })(links[i]);
  }
}

/* ---------- session_end (fires on unload) ---------- */
function wireSessionEnd(){
  var send = function(){
    fire("session_end", { visitor_id: getVisitor(), session_id: getSession(), ts: nowIso() });
  };
  if (document.visibilityState !== undefined) {
    document.addEventListener("visibilitychange", function(){
      if (document.visibilityState === "hidden") send();
    });
  }
  window.addEventListener("pagehide", send);
}

/* ============================================================
   THE BEACON // easter egg (ORDER 1) // ported from Rockwell preview
   ============================================================ */
function initEgg(){
  var egg = document.getElementById("egg");
  var modal = document.getElementById("egg-modal");
  var toasts = document.getElementById("toasts");
  var reels = document.getElementById("reels");
  var codeDrop = document.getElementById("code-drop");
  var continueBtn = document.getElementById("egg-continue");
  if (!egg || !modal || !reels) return;

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var CORNERS = ["br","tr","bl","tl"];
  var KEY_HITS = "pp_egg_hits", KEY_CLAIMED = "pp_egg_claimed";
  var CODE = "PATRIOT25";
  var hits = parseInt(sessionStorage.getItem(KEY_HITS)||"0",10);
  var claimed = sessionStorage.getItem(KEY_CLAIMED)==="1";

  function toast(msg, hot){
    var t = document.createElement("div");
    t.className = "toast" + (hot ? " hot" : ""); t.textContent = msg;
    toasts.appendChild(t);
    setTimeout(function(){ if (t.parentNode) t.parentNode.removeChild(t); }, 3400);
  }
  function setHits(n){
    hits = n; sessionStorage.setItem(KEY_HITS, String(n));
    egg.setAttribute("aria-label","Hidden mission cache. "+n+" of 5 signals acquired.");
    if (n>0) egg.setAttribute("data-hits","1"); else egg.removeAttribute("data-hits");
  }
  function relocate(){
    var cur = CORNERS.indexOf(egg.getAttribute("data-corner"));
    var next = CORNERS[(cur+1)%CORNERS.length];
    egg.setAttribute("data-corner", next);
  }
  /* build reels: one strip per char of PATRIOT25 */
  var CHARS = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  function buildReels(){
    var html = "";
    for (var i=0;i<CODE.length;i++){
      var spins = 8 + (i*3)%5;
      var symbols = "";
      for (var s=0;s<spins*2+3;s++){ symbols += '<span'+(s===spins*2+2?' class="hit"':'')+'>'+CHARS[(i*7+s*13)%CHARS.length]+'</span>'; }
      html += '<div class="reel" data-idx="'+i+'" data-spins="'+spins+'"><div class="strip">'+symbols+'</div></div>';
    }
    reels.innerHTML = html;
  }
  buildReels();
  function spinReels(cb){
    var strips = reels.querySelectorAll(".strip");
    var done = 0;
    function settle(strip, spins, idx){
      var h = 42;
      var extra = (idx%3)*3;
      var offset = (spins+extra)*h;
      if (reduced) {
        strip.style.transform = "translateY(-"+offset+"px)";
        var hs = strip.querySelectorAll("span")[spins*2+2];
        if (hs) hs.classList.add("hit");
        done++;
        if (done===strips.length) cb();
        return;
      }
      strip.style.transition = "transform "+(0.9+idx*0.14)+"s cubic-bezier(.2,.7,.2,1)";
      strip.style.transform = "translateY(-"+offset+"px)";
      setTimeout(function(){
        var s = strip.querySelectorAll("span")[spins*2+2];
        if (s) s.classList.add("hit");
        done++;
        if (done===strips.length) cb();
      },(0.95+idx*0.14)*1000);
    }
    strips.forEach(function(strip, idx){
      var spins = parseInt(strip.parentNode.getAttribute("data-spins"),10);
      settle(strip, spins, idx);
    });
  }

  egg.addEventListener("click", function(){
    trackEvent("easter_egg_click", { clicks: hits+1 });
    if (claimed){ toast("Cache already secured this session. Keep hunting, soldier."); return; }
    egg.classList.remove("pop"); void egg.offsetWidth; egg.classList.add("pop");
    var n = hits + 1;
    if (n===3) toast("SIGNAL ACQUIRED. Two more hits to unlock the cache.");
    if (n===4) toast("TARGET COMPROMISED. One more hit.", true);
    if (n>=5){
      claimed = true; sessionStorage.setItem(KEY_CLAIMED,"1");
      setHits(0);
      trackEvent("easter_egg_reveal", { clicks: 5, reward: "25_dollars", relocated_to: CORNERS[(CORNERS.indexOf(egg.getAttribute("data-corner"))+1)%CORNERS.length] });
      modal.classList.add("open");
      var burst = modal.querySelector(".burst");
      var rays = modal.querySelector(".rays");
      var drop = codeDrop;
      drop.classList.remove("on");
      burst.classList.remove("on"); rays.classList.remove("on");
      setTimeout(function(){ spinReels(function(){
        burst.classList.add("on"); rays.classList.add("on");
        setTimeout(function(){ drop.classList.add("on"); }, 260);
      }); }, 80);
      return;
    }
    setHits(n);
  });
  continueBtn.addEventListener("click", function(){
    modal.classList.remove("open");
    relocate();
    toast("New location acquired. Good hunting, soldier.");
  });
  document.addEventListener("keydown", function(e){
    if (e.key==="Escape" && modal.classList.contains("open")){
      modal.classList.remove("open"); relocate(); toast("New location acquired. Good hunting, soldier.");
    }
  });
}

/* ---------- boot ---------- */
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function(){ fireView(); wireLinks(); wireSessionEnd(); initEgg(); });
} else {
  fireView(); wireLinks(); wireSessionEnd(); initEgg();
}
})();
