package marketing

import (
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module serves patriotic marketing pages with the tactical theme
// (olive/khaki/paper/orange, Black Ops One, bugfield canvas, crosshair hero).
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
	return true
}

func (m *Module) home(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	// TODO: templ render layouts/main with hero crosshair + bugfield
	_, _ = w.Write([]byte(`<!doctype html><html><head><title>Patriot Pest Control</title><link rel="stylesheet" href="/assets/styles.css"></head><body><h1>Patriot Pest Control — Go (coming soon)</h1><p>Module: marketing — <a href="/health">/health</a></p></body></html>`))
}
func (m *Module) page(name string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		http.ServeFile(w, r, "internal/view/assets/"+name+".html")
	}
}
func (m *Module) pest(w http.ResponseWriter, r *http.Request)      { http.NotFound(w, r) }
func (m *Module) area(w http.ResponseWriter, r *http.Request)      { http.NotFound(w, r) }
func (m *Module) blogIndex(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) blogPost(w http.ResponseWriter, r *http.Request)  { http.NotFound(w, r) }
func (m *Module) rss(w http.ResponseWriter, r *http.Request)       { http.NotFound(w, r) }
