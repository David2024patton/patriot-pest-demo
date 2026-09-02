// Package view renders the tactical front-end: the shared page shell (SEO head,
// nav, footer) wrapping each page template, plus the light authenticated app
// shell. It mirrors templates/layouts/main.php and app.php pixel-for-pixel.
package view

import (
	"bytes"
	"encoding/json"
	"fmt"
	"html/template"
	"log"
	"net/http"
	"strings"
	"time"

	"github.com/David2024patton/patriot-pest-go/internal/data"
)

// ---- FuncMap (stateless helpers; per-request values come in the data map) ----

var funcMap = template.FuncMap{
	"asset":  func(p string) string { return "/assets/" + p },
	"upper":  strings.ToUpper,
	"lower":  strings.ToLower,
	"ucfirst": func(s string) string {
		if s == "" {
			return s
		}
		return strings.ToUpper(s[:1]) + s[1:]
	},
	"p3": func(n int) string { return fmt.Sprintf("%03d", n) },
	"raw":  func(v any) template.HTML { return template.HTML(fmt.Sprintf("%v", v)) },
	"jld":  func(v any) template.HTML { b, _ := json.Marshal(v); return template.HTML(string(b)) },
	"json": func(v any) template.HTML { b, _ := json.Marshal(v); return template.HTML(string(b)) },
	// date formats for published_at / created strings
	"dateMD": func(s string) string {
		t := parseDate(s)
		if t.IsZero() {
			return ""
		}
		return t.Format("Jan 2")
	},
	"dateMDY": func(s string) string {
		t := parseDate(s)
		if t.IsZero() {
			return ""
		}
		return t.Format("Jan 2, 2006")
	},
	"dateFM": func(s string) string {
		t := parseDate(s)
		if t.IsZero() {
			return ""
		}
		return t.Format("January 2, 2006")
	},
	"int": func(v any) int {
		switch x := v.(type) {
		case int:
			return x
		case float64:
			return int(x)
		}
		return 0
	},
	"sub":  func(a, b int) int { return a - b },
	"add":  func(a, b int) int { return a + b },
	"cityslug": data.CitySlug,
	"pestimg":  func(f string) string { return "/assets/img/pests/" + f },
	// Category-specific copy for the pest threat file (see pages_pest.go).
	"pestcopy": PestCopyFor,
}

func parseDate(s string) time.Time {
	if s == "" {
		return time.Time{}
	}
	for _, l := range []string{"2006-01-02 15:04:05", "2006-01-02T15:04:05", "2006-01-02"} {
		if t, err := time.Parse(l, s); err == nil {
			return t
		}
	}
	return time.Time{}
}

// ---- layout + page templates ----

// The layout shell (from templates/layouts/main.php). .PageBody is the
// pre-rendered page-body HTML (see render); .Page names the active page.
const layoutHTML = `<!DOCTYPE html>
<html lang="en">
<head>
  {{template "analytics" .}}
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{.Title}}</title>
  {{if .Description}}<meta name="description" content="{{.Description}}">{{end}}
  {{if .Keywords}}<meta name="keywords" content="{{.Keywords}}">{{end}}
  <meta name="robots" content="{{.Robots}}">
  <link rel="canonical" href="{{.Canonical}}">
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#1c2415">
  <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Patriot Pest Control">
  <meta property="og:title" content="{{.Title}}">
  <meta property="og:description" content="{{.Description}}">
  <meta property="og:url" content="{{.Canonical}}">
  <meta property="og:image" content="{{.OGImage}}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{.Title}}">
  <meta name="twitter:description" content="{{.Description}}">
  {{range .JSONLD}}<script type="application/ld+json">{{jld .}}</script>
  {{end}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/styles.css">
  {{if .AppUI}}<link rel="stylesheet" href="/assets/admin.css">{{end}}
  <link rel="stylesheet" href="/assets/beacon.css">
  <link rel="stylesheet" href="/assets/pwa-install.css">
  <link rel="icon" href="/assets/img/pests/ants.jpg" type="image/jpeg">
</head>
<body{{if .BodyClass}} class="{{.BodyClass}}"{{end}}>

<nav class="top-nav" aria-label="Main navigation">
  <a class="brand" href="/"><span class="star">★</span> PATRIOT PEST CONTROL</a>
  <button id="menu-btn" aria-label="Toggle menu">☰ Menu</button>
  <div class="navlinks">
    <a class="nl{{if eq .Page "home"}} active{{end}}" href="/">Home</a><a class="nl" href="/about">About</a><a class="nl" href="/services">Services</a><a class="nl" href="/prices">Prices</a><a class="nl" href="/service-areas">Areas</a><a class="nl" href="/blogs">Blog</a><a class="nl" href="/faqs">FAQs</a><a class="nl" href="/contact">Contact</a><a class="nl" href="/links">🔗 All Links</a>
    {{if eq .UserType "customer"}}<a class="nl" href="/customer-dashboard">My Account</a>
    {{else if eq .UserType "staff"}}<a class="nl" href="/staff-dashboard">Dashboard</a>{{if .IsAdmin}}<a class="nl" href="/admin">Admin</a>{{end}}
    {{else}}<a class="nl" href="/login">Sign In</a>{{end}}
    <a class="nav-cta" href="{{.PhoneHref}}">☎ {{.PhoneDisplay}}</a>
  </div>
</nav>

<main>
{{if and .Crumb (gt (len .Crumb) 1)}}
  <div class="wrap" style="padding-top:1.4rem">
    <nav class="crumb" aria-label="Breadcrumb">
      {{range $i, $c := .Crumb}}{{if $i}}<span class="sep">/</span> {{end}}{{if lt $i (sub (len $.Crumb) 1)}}<a href="{{index $c 1}}">{{index $c 0}}</a>{{else}}<span>{{index $c 0}}</span>{{end}}{{end}}
    </nav>
  </div>
{{end}}
{{.PageBody}}
</main>

<!-- Mobile sticky navigation (marketing pages only) -->
<nav class="mobile-sticky-nav" aria-label="Mobile quick navigation">
  <a href="javascript:history.back()" aria-label="Back">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
    <span class="label">Back</span>
  </a>
  <a href="javascript:history.forward()" aria-label="Forward">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
    <span class="label">Next</span>
  </a>
  <a href="/" aria-label="Home">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/></svg>
    <span class="label">Home</span>
  </a>
  <a href="/search" aria-label="Search">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>
    <span class="label">Search</span>
  </a>
  <a href="{{.PhoneHref}}" aria-label="Call">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/></svg>
    <span class="label">Call</span>
  </a>
  <a href="/contact" aria-label="Contact">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414zM0 4.697v7.104l5.803-3.558zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.143l-6.57-4.027L8 9.586zm1.964.372 6.57 4.027A2 2 0 0 0 16 13.802V4.697l-5.803 3.546z"/></svg>
    <span class="label">Contact</span>
  </a>
  <a href="/login" aria-label="Login">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/></svg>
    <span class="label">Login</span>
  </a>
</nav>
<div class="mobile-nav-spacer"></div>

<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <div class="foot-brand"><span style="color:var(--orange)">★</span> PATRIOT PEST CONTROL</div>
        <p>Veteran-owned pest control for homes &amp; businesses across Washington, Idaho, Oregon &amp; Arizona. Founded by U.S. Military Veteran Skyler Rose. Eco-friendly, family &amp; pet safe, 100% satisfaction guaranteed.</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <a href="/about">About Us</a><a href="/services">Services</a>
        <a href="/prices">Pricing</a><a href="/blogs">Blog</a>
        <a href="/faqs">FAQs</a><a href="/referral">Referral Program</a>
        <a href="/help">Help Center</a>
      </div>
      <div>
        <h4>Top Services</h4>
        <a href="/pest/ants">Ant Control</a><a href="/pest/termites">Termite Control</a>
        <a href="/pest/bed-bugs">Bed Bug Treatment</a><a href="/pest/rodents">Rodent Control</a>
        <a href="/pest/mosquitoes">Mosquito Control</a><a href="/pest/wasps">Wasp Removal</a>
      </div>
      <div>
        <h4>Contact</h4>
        <a href="{{.PhoneHref}}">{{.PhoneDisplay}} - {{.PhoneLabel}}</a>
        <a href="{{.OtherTel}}">{{.OtherDisplay}} - {{.OtherLabel}}</a>
        <a href="mailto:info@patriotpest.pro">info@patriotpest.pro</a>
        <a href="/contact">Spokane, WA 99201, United States</a>
        <a href="/socials">Social Media</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© {{.Year}} PATRIOT PEST CONTROL · ALL RIGHTS RESERVED</span>
      <span><a href="/privacy-policy">PRIVACY</a> · <a href="/terms-of-use">TERMS</a> · <a href="/sitemap">SITEMAP</a></span>
      <span>🇺🇸 VETERAN-OWNED AMERICAN COMPANY</span>
    </div>
  </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="/assets/main.js"></script>
{{if .Track}}<script src="/assets/beacon.js"></script>{{end}}
{{template "install-banner" .}}
<script src="/assets/pwa-install.js"></script>
{{if .AppUI}}
<!-- Phase 2: Global Command Bar (Ctrl+K) + SSE live feed HUD -->
<div id="cmdk-overlay" hidden>
  <div id="cmdk-box" role="dialog" aria-label="Command bar">
    <input id="cmdk-input" type="text" placeholder="Search customers, pests, pages…  (Esc to close)" autocomplete="off">
    <div id="cmdk-results"></div>
  </div>
</div>
<div id="hud-toasts" aria-live="polite"></div>
<style>
#cmdk-overlay{position:fixed;inset:0;background:rgba(10,14,7,.72);backdrop-filter:blur(3px);z-index:9999;display:flex;justify-content:center;padding-top:12vh}
#cmdk-overlay[hidden]{display:none}
#cmdk-box{width:min(640px,92vw);height:fit-content;background:var(--olive-900);border:1px solid var(--olive-700);border-radius:12px;box-shadow:0 24px 64px rgba(0,0,0,.5);overflow:hidden}
#cmdk-input{width:100%;padding:1rem 1.2rem;background:transparent;border:0;outline:0;color:var(--cream);font:600 1rem var(--body)}
#cmdk-results{max-height:52vh;overflow-y:auto;border-top:1px solid var(--olive-700)}
.cmdk-group{padding:.5rem 1.2rem .2rem;font:600 .6rem var(--mono);letter-spacing:.2em;color:var(--olive-300)}
.cmdk-item{display:block;padding:.55rem 1.2rem;color:var(--khaki);text-decoration:none;border-left:3px solid transparent}
.cmdk-item:hover,.cmdk-item.sel{background:var(--olive-800);color:var(--cream);border-left-color:var(--orange)}
.cmdk-item small{display:block;color:var(--olive-300);font-family:var(--mono);font-size:.66rem}
#hud-toasts{position:fixed;right:1rem;bottom:1rem;display:flex;flex-direction:column;gap:.5rem;z-index:9998}
.hud-toast{background:var(--olive-800);border:1px solid var(--olive-500);border-left:4px solid var(--orange);color:var(--cream);padding:.65rem .9rem;border-radius:8px;font-size:.82rem;box-shadow:0 8px 24px rgba(0,0,0,.4);animation:hudin .25s ease}
.hud-toast small{display:block;color:var(--olive-300);font-family:var(--mono)}
@keyframes hudin{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:none}}
</style>
<script>
(function () {
  var ov = document.getElementById('cmdk-overlay');
  var input = document.getElementById('cmdk-input');
  var results = document.getElementById('cmdk-results');
  var items = [], sel = 0, timer = null;

  function open() { ov.hidden = false; input.value = ''; results.innerHTML = ''; items = []; sel = 0; input.focus(); }
  function close() { ov.hidden = true; }

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ov.hidden ? open() : close(); }
    if (e.key === 'Escape' && !ov.hidden) close();
    if (!ov.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % (items.length || 1);
      items.forEach(function (el, i) { el.classList.toggle('sel', i === sel); });
    }
    if (!ov.hidden && e.key === 'Enter' && items[sel]) window.location = items[sel].href;
  });
  ov.addEventListener('click', function (e) { if (e.target === ov) close(); });

  function render(data) {
    results.innerHTML = ''; items = []; sel = 0;
    var groups = [['customers', 'CUSTOMERS'], ['pests', 'PESTS'], ['posts', 'BLOG'], ['pages', 'PAGES']];
    groups.forEach(function (g) {
      var list = data[g[0]] || [];
      if (!list.length) return;
      var h = document.createElement('div'); h.className = 'cmdk-group'; h.textContent = g[1];
      results.appendChild(h);
      list.forEach(function (row) {
        var a = document.createElement('a');
        a.className = 'cmdk-item'; a.href = row.url;
        a.innerHTML = '<span>' + row.title + '</span>' + (row.sub ? '<small>' + row.sub + '</small>' : '');
        results.appendChild(a); items.push(a);
      });
    });
    if (!items.length) {
      results.innerHTML = '<div class="cmdk-group">NO MATCHES</div>';
    } else {
      items[0].classList.add('sel');
    }
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (!q) { results.innerHTML = ''; items = []; return; }
    timer = setTimeout(function () {
      fetch('/api/command?q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(render).catch(function () {});
    }, 180);
  });

  // SSE live feed HUD (staff surfaces).
  if (window.EventSource) {
    var es = new EventSource('/api/staff/events');
    function toast(ev) {
      var box = document.getElementById('hud-toasts');
      var t = document.createElement('div');
      t.className = 'hud-toast';
      t.innerHTML = '<small>' + ev.at + ' · ' + ev.type.toUpperCase() + '</small>' + ev.text;
      box.appendChild(t);
      setTimeout(function () { t.remove(); }, 6000);
    }
    ['message', 'move', 'call', 'lead'].forEach(function (k) {
      es.addEventListener(k, function (e) { try { toast(JSON.parse(e.data)); } catch (_) {} });
    });
    es.onmessage = function (e) { try { toast(JSON.parse(e.data)); } catch (_) {} };
  }
})();
</script>
{{end}}
</body>
</html>
`

// Named sub-templates that must exist in the set.
const namedTemplates = `
{{define "analytics"}}{{if .AnalyticsOn}}
{{if .GTag}}<script async src="https://www.googletagmanager.com/gtag/js?id={{.LoaderID}}"></script>
{{end}}<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  {{if .GTag}}gtag('config', "{{.GTag}}", { 'send_page_view': true, 'anonymize_ip': true });{{end}}
  {{if .GAds}}gtag('config', "{{.GAds}}");{{end}}
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="tel:"]').forEach(function (link) {
      link.addEventListener('click', function () { gtag('event', 'phone_call', { 'event_category': 'engagement', 'event_label': 'Phone Click' }); });
    });
    document.querySelectorAll('[data-track]').forEach(function (el) {
      el.addEventListener('click', function () { gtag('event', el.dataset.track, { 'event_category': 'engagement' }); });
    });
  });
</script>
{{if .FBPixel}}<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', "{{.FBPixel}}");
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{.FBPixel}}&ev=PageView&noscript=1"></noscript>
{{end}}{{if .Clarity}}<script>
(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script',"{{.Clarity}}");
</script>
{{end}}{{end}}{{end}}
{{define "install-banner"}}
<div class="ppc-install" id="ppc-install-banner" role="dialog" aria-live="polite" aria-label="Install app">
  <div class="ppc-install-card">
    <img class="ppc-install-ico" src="/assets/icons/icon-192.png" alt="" width="48" height="48">
    <div class="ppc-install-copy">
      <span class="ppc-install-tag">APP</span>
      <strong>Install Patriot Pest</strong>
      <span>One tap adds it to your home screen for instant access.</span>
    </div>
    <div class="ppc-install-actions">
      <button type="button" class="ppc-install-btn" id="ppc-install-btn">Install</button>
      <button type="button" class="ppc-install-x" id="ppc-install-dismiss" aria-label="Dismiss install prompt">&times;</button>
    </div>
  </div>
</div>
{{end}}
`

// pageTemplates holds every marketing page body, keyed by the .Page name.
var pageTemplates = map[string]string{}

// RegisterPageTemplate defines a page body template by name.
func RegisterPageTemplate(name, content string) {
	pageTemplates[name] = "{{define \"" + name + "\"}}" + content + "{{end}}"
}

// compile builds the shared template set (layout + all pages + named parts).
func compile() (*template.Template, error) {
	var b strings.Builder
	b.WriteString(namedTemplates)
	b.WriteString(layoutHTML)
	for _, t := range pageTemplates {
		b.WriteString("\n")
		b.WriteString(t)
	}
	return template.New("root").Funcs(funcMap).Parse(b.String())
}

// withBase merges base SEO/nav fields + phone into a data map for a page.
func withBase(r *http.Request, page, title, description, keywords string) map[string]any {
	ph := PhoneFor(r)
	d := map[string]any{
		"Page":         page,
		"Title":        title,
		"Description":  description,
		"Keywords":     keywords,
		"Robots":       "index, follow, max-snippet:-1",
		"Canonical":    canonical(r),
		"OGImage":      "https://go.patriotpest.pro/assets/img/og.png",
		"JSONLD":       []any{},
		// tel: links are wrapped in template.URL so Go 1.26's context-aware
		// href filter (http/https/mailto only) passes them through untouched.
		"PhoneDisplay": ph.Display,
		"PhoneHref":    template.URL(ph.Tel),
		"PhoneLabel":   ph.Label,
		"OtherDisplay": ph.Other.Display,
		"OtherTel":     template.URL("tel:" + ph.Other.Tel),
		"OtherLabel":   ph.Other.Label,
		"IsAZ":         ph.IsAZ,
		"Year":         time.Now().Year(),
		"UserType":     "",
		"IsAdmin":      false,
		"AppUI":        false,
		"BodyClass":    "",
		"Track":        true,
		"Crumb":        [][2]string{},
	}
	return d
}

// canonical builds the absolute canonical URL for the current request.
func canonical(r *http.Request) string {
	host := r.Host
	scheme := "https"
	if r.Header.Get("X-Forwarded-Proto") == "http" {
		scheme = "http"
	}
	if host == "" {
		host = "go.patriotpest.pro"
	}
	return scheme + "://" + host + r.URL.Path
}

// render runs the layout for a page, merging page-specific data into base.
// Go 1.26's template parser only accepts static names in {{template ...}}, so
// the page body is rendered first via ExecuteTemplate and embedded as raw HTML.
func render(w http.ResponseWriter, r *http.Request, d map[string]any, status int) {
	tpl, err := compile()
	if err != nil {
		http.Error(w, "template error: "+err.Error(), http.StatusInternalServerError)
		return
	}
	page, _ := d["Page"].(string)
	var buf bytes.Buffer
	if err := tpl.ExecuteTemplate(&buf, page, d); err != nil {
		http.Error(w, "template error: "+err.Error(), http.StatusInternalServerError)
		return
	}
	d["PageBody"] = template.HTML(buf.String())
	if status != http.StatusOK {
		w.WriteHeader(status)
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := tpl.Execute(w, d); err != nil {
		log.Printf("view: template exec error: %v", err)
	}
}
