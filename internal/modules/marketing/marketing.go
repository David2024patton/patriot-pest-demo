package marketing

import (
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module serves patriotic tactical theme — pixel-identical to PHP styles.css/app.css/main.js
// Crosshair #xh-v #xh-h #xh-ring in main.js:79 hero mousemove, bugfield 56 bugs, grain, progress.
type Module struct {
	Enabled bool
}

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/", m.home)
	r.Get("/about", m.page("about"))
	r.Get("/services", m.page("services"))
	r.Get("/prices", m.page("prices"))
	r.Get("/service-areas", m.page("areas"))
	r.Get("/faqs", m.page("faqs"))
	r.Get("/contact", m.page("contact"))
	r.Get("/pest/{slug}", m.pest)
	r.Get("/areas/{slug}", m.area)
	r.Get("/blogs", m.blogIndex)
	r.Get("/blogs/{slug}", m.blogPost)
	r.Get("/blogs/rss.xml", m.rss)
	r.Get("/blog/rss.xml", m.rss)
	// Assets — serve identical tactical assets
	r.Handle("/assets/*", http.StripPrefix("/assets/", http.FileServer(http.Dir("internal/view/assets"))))
	return true
}

func (m *Module) home(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(`<!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Patriot Pest Control — Veteran Owned — Spokane WA</title>
<link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Barlow:wght@400;600&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/styles.css"><link rel="stylesheet" href="/assets/app.css">
</head><body>
<canvas id="bugfield"></canvas><div class="grain"></div><div id="progress"></div>
<nav><a class="brand" href="/"><span class="star">★</span> PATRIOT PEST CONTROL</a>
<div class="navlinks"><a class="nl active" href="/">Home</a><a class="nl" href="/services">Services</a><a class="nl" href="/prices">Prices</a><a class="nl" href="/service-areas">Areas</a><a class="nl" href="/contact">Contact</a></div>
<a class="nav-cta" href="tel:+15095551234">CALL NOW</a><button id="menu-btn">☰ Menu</button></nav>
<section id="hero" style="position:relative;min-height:92vh;display:grid;place-items:center;padding-top:64px;overflow:hidden">
<div id="xh-v" style="position:absolute;top:0;bottom:0;width:1px;background:rgba(244,119,46,.9);left:50%;pointer-events:none"></div>
<div id="xh-h" style="position:absolute;left:0;right:0;height:1px;background:rgba(244,119,46,.9);top:50%;pointer-events:none"></div>
<div id="xh-ring" style="position:absolute;width:48px;height:48px;border:1px solid rgba(244,119,46,.9);border-radius:50%;left:50%;top:50%;transform:translate(-50%,-50%);pointer-events:none"></div>
<div style="text-align:center;z-index:2">
<div style="font-family:var(--mono);font-size:.7rem;letter-spacing:.2em;color:var(--khaki)">VETERAN OWNED • SPOKANE WA</div>
<h1 style="font-family:var(--display);font-size:clamp(2.4rem,7vw,4.8rem);color:var(--cream);margin:.4rem 0">MISSION: PEST-FREE HOME</h1>
<p id="brief-lines" data-lines='["OPERATION ......... PEST-FREE HOME","COMMANDER ......... SKYLER ROSE","STATUS ............ >> GO FOR LAUNCH"]' style="font-family:var(--mono);color:var(--khaki);font-size:.8rem"></p>
<a class="nav-cta" href="/contact" style="display:inline-block;margin-top:1.2rem">REQUEST SERVICE</a>
</div>
</section>
<section style="padding:3rem 2rem;max-width:900px;margin:0 auto">
<h2 style="font-family:var(--display);color:var(--orange)">Why Patriot</h2>
<p style="color:var(--paper)">Same-day, 90-day warranty, family & pet safe. Unified 7-channel inbox, kanban, supply OSHA, RAG Ask AI.</p>
</section>
<div id="hud-clock" style="position:fixed;bottom:12px;right:12px;font-family:var(--mono);font-size:.65rem;color:var(--khaki);z-index:90"></div>
<script src="/assets/main.js"></script><script src="/assets/beacon.js"></script>
</body></html>`))
}
func (m *Module) page(name string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/html; charset=utf-8")
		w.Write([]byte(`<!doctype html><html><head><link rel="stylesheet" href="/assets/styles.css"></head><body><nav><a class="brand" href="/">PATRIOT</a></nav><main style="padding:96px 2rem"><h1 style="font-family:var(--display)">` + name + `</h1><p>Patriotic tactical theme — ` + name + `</p><canvas id="bugfield"></canvas><script src="/assets/main.js"></script></main></body></html>`))
	}
}
func (m *Module) pest(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><html><head><link rel="stylesheet" href="/assets/styles.css"></head><body><main style="padding:96px 2rem"><h1>Pest ` + chi.URLParam(r, "slug") + `</h1><script type="application/ld+json">{"@type":"PestControlService"}</script><canvas id="bugfield"></canvas><script src="/assets/main.js"></script></main></body></html>`))
}
func (m *Module) area(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><html><head><link rel="stylesheet" href="/assets/styles.css"></head><body><main style="padding:96px 2rem"><h1>Area ` + chi.URLParam(r, "slug") + `</h1><script type="application/ld+json">{"@type":"LocalBusiness"}</script><canvas id="bugfield"></canvas><script src="/assets/main.js"></script></main></body></html>`))
}
func (m *Module) blogIndex(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><html><head><link rel="stylesheet" href="/assets/styles.css"></head><body><main style="padding:96px 2rem"><h1>Blogs</h1><a href="/blogs/rss.xml">RSS</a><canvas id="bugfield"></canvas><script src="/assets/main.js"></script></main></body></html>`))
}
func (m *Module) blogPost(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><html><head><link rel="stylesheet" href="/assets/styles.css"></head><body><main style="padding:96px 2rem"><h1>Blog ` + chi.URLParam(r, "slug") + `</h1><canvas id="bugfield"></canvas><script src="/assets/main.js"></script></main></body></html>`))
}
func (m *Module) rss(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/rss+xml")
	w.Write([]byte(`<?xml version="1.0"?><rss version="2.0"><channel><title>Patriot Pest Control</title><link>https://go.patriotpest.pro</link><item><title>Why quarterly</title></item></channel></rss>`))
}
