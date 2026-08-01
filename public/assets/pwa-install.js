/* ============================================================
   pwa-install.js - PWA install prompt.
   1. Registers the service worker (secure contexts only).
   2. Holds the beforeinstallprompt event (deferred).
   3. Shows the banner only on mobile/tablet widths, only when the
      browser offers install, and only if the user has not dismissed
      it before. The native prompt() call happens only on a tap of
      the Install button (user gesture).
   ============================================================ */
(function () {
  "use strict";

  var LS_KEY = "ppc_install_dismissed";
  var TABLET_MAX = 1024;
  var banner = document.getElementById("ppc-install-banner");
  var btn = document.getElementById("ppc-install-btn");
  var xBtn = document.getElementById("ppc-install-dismiss");
  var deferred = null;

  /* ---------- service worker registration ---------- */
  if ("serviceWorker" in navigator &&
      (location.protocol === "https:" ||
       location.hostname === "localhost" ||
       location.hostname === "127.0.0.1")) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("/sw.js").catch(function () {
        /* Offline support is progressive; never break the page. */
      });
    });
  }

  if (!banner || !btn || !xBtn) { return; }

  function isMobileOrTablet() {
    return window.matchMedia("(max-width:" + TABLET_MAX + "px)").matches;
  }

  function wasDismissed() {
    try { return localStorage.getItem(LS_KEY) === "1"; } catch (e) { return false; }
  }

  function markDismissed() {
    try { localStorage.setItem(LS_KEY, "1"); } catch (e) { /* private mode */ }
  }

  function show() {
    if (isMobileOrTablet() && !wasDismissed()) {
      banner.classList.add("is-open");
    }
  }

  function hide() {
    banner.classList.remove("is-open");
  }

  /* ---------- deferred install prompt ---------- */
  window.addEventListener("beforeinstallprompt", function (event) {
    event.preventDefault();
    deferred = event;
    show();
  });

  btn.addEventListener("click", function () {
    hide();
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.then(function (choice) {
        if (choice.outcome === "accepted") { markDismissed(); }
        deferred = null;
      }).catch(function () { deferred = null; });
    }
  });

  xBtn.addEventListener("click", function () {
    hide();
    markDismissed();
  });

  /* ---------- already installed: never ask again ---------- */
  window.addEventListener("appinstalled", function () {
    hide();
    markDismissed();
  });

  /* ---------- viewport crossing: hide on desktop, keep hidden ---------- */
  var mq = window.matchMedia("(max-width:" + TABLET_MAX + "px)");
  var onMq = function (e) { if (!e.matches) { hide(); } };
  if (mq.addEventListener) { mq.addEventListener("change", onMq); }
  else if (mq.addListener) { mq.addListener(onMq); }
})();
