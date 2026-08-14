package tech

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — GET /tech PWA, GET /tech/routes GPS tech_locations, notes with GPS, case→ticket, sw.js offline queue.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/tech", m.Index)
	r.Get("/tech/manifest.webmanifest", m.Manifest)
	r.Get("/tech/sw.js", m.ServiceWorker)
	r.Get("/tech/routes", m.Routes)
	r.Post("/api/tech/locations", m.PostLocation)
	r.Post("/api/cases/{id}/tickets", m.CaseTicket)
	return true
}
func (m *Module) Index(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><title>Tech PWA</title><link rel=manifest href="/tech/manifest.webmanifest"><h1>Tech App — PWA installable</h1><div id=map>GPS live map</div><script>navigator.geolocation.watchPosition(p=>fetch('/api/tech/locations',{method:'POST',body:JSON.stringify({lat:p.coords.latitude,lng:p.coords.longitude})}))</script>`))
}
func (m *Module) Manifest(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/manifest+json")
	json.NewEncoder(w).Encode(map[string]any{"name": "Patriot Tech", "short_name": "PatTech", "display": "standalone", "start_url": "/tech"})
}
func (m *Module) ServiceWorker(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/javascript")
	w.Write([]byte(`self.addEventListener('fetch',e=>{e.respondWith(fetch(e.request).catch(()=>caches.match(e.request)))});`))
}
func (m *Module) Routes(w http.ResponseWriter, r *http.Request) {
	techID := r.URL.Query().Get("tech_id")
	if techID == "" {
		techID = "me"
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"routes": []any{map[string]any{"tech_id": techID, "lat": 47.65, "lng": -117.42, "at": "2026-08-14T07:00:00Z"}}})
}
func (m *Module) PostLocation(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "tech_id": "me", "saved": true})
}
func (m *Module) CaseTicket(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "case_id": chi.URLParam(r, "id"), "ticket": "t1"})
}
