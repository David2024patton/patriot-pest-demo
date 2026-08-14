/* app.js - light app shell behaviors: mobile drawer + customer search overlay.
   No dependencies. Loaded only on authenticated app routes (layouts/app.php). */
(function () {
  'use strict';
  var body = document.body;

  /* ---------- mobile drawer ---------- */
  var burger = document.getElementById('appshell-burger');
  var scrim  = document.getElementById('appshell-scrim');
  function openDrawer()  { body.classList.add('drawer-open'); }
  function closeDrawer() { body.classList.remove('drawer-open'); }
  if (burger) burger.addEventListener('click', function (e) { e.stopPropagation(); openDrawer(); });
  if (scrim)  scrim.addEventListener('click', closeDrawer);

  /* ---------- customer search overlay (staff/admin) ---------- */
  var overlay = document.getElementById('csearch');
  var openBtn = document.getElementById('csearch-open');
  var input   = document.getElementById('csearch-input');
  var results = document.getElementById('csearch-results');
  if (!overlay || !input || !results) return;

  var timer = null;
  var lastQ = '';

  function openSearch() {
    overlay.classList.add('open');
    setTimeout(function () { input.focus(); }, 30);
  }
  function closeSearch() {
    overlay.classList.remove('open');
    input.value = '';
    lastQ = '';
    results.innerHTML = '<div class="csearch-empty">Start typing to search the customer book.</div>';
  }

  if (openBtn) openBtn.addEventListener('click', openSearch);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeSearch(); });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeSearch(); closeDrawer(); }
    /* "/" focuses search from anywhere in the app (staff only) */
    if (e.key === '/' && !overlay.classList.contains('open') &&
        !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
      e.preventDefault(); openSearch();
    }
  });

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function initials(name) {
    var p = String(name || '').trim().split(/\s+/);
    return ((p[0] || 'U')[0] + (p[1] ? p[1][0] : '')).toUpperCase();
  }

  function render(list) {
    if (!list.length) {
      results.innerHTML = '<div class="csearch-empty">No customers match that search.</div>';
      return;
    }
    results.innerHTML = list.map(function (c) {
      var meta = [c.account_number, c.phone, c.city].filter(Boolean).join(' · ');
      var status = c.status || 'active';
      return '<a class="cs-item" href="/staff/customers/' + encodeURIComponent(c.id) + '">' +
        '<span class="cs-av">' + esc(initials(c.name)) + '</span>' +
        '<span><span class="cs-name">' + esc(c.name || 'N/A') + '</span>' +
        '<span class="cs-meta">' + esc(meta) + '</span></span>' +
        '<span class="cs-status badge ' + esc(status) + '">' + esc(status) + '</span>' +
        '</a>';
    }).join('');
  }

  function search(q) {
    q = q.trim();
    if (q === lastQ) return;
    lastQ = q;
    if (q.length < 2) {
      results.innerHTML = '<div class="csearch-empty">Type at least 2 characters.</div>';
      return;
    }
    results.innerHTML = '<div class="csearch-loading">Searching…</div>';
    fetch('/api/customer-search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) { render(Array.isArray(data) ? data : (data.results || [])); })
      .catch(function () {
        results.innerHTML = '<div class="csearch-empty">Search failed. Please try again.</div>';
      });
  }

  input.addEventListener('input', function () {
    var q = input.value;
    clearTimeout(timer);
    timer = setTimeout(function () { search(q); }, 220);
  });
})();
