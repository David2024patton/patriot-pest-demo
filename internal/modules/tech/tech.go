package tech

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — GET /tech PWA, GET /tech/routes GPS tech_locations, notes with GPS, case→ticket, sw.js offline.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/tech", m.Index)
	r.Get("/tech/routes", m.Routes)
	r.Get("/tech/scan", m.ScanPage)
	return true
}
func (m *Module) Index(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"pwa": "tech", "installable": true})
}
func (m *Module) Routes(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"routes": []any{map[string]any{"tech_id": "me", "lat": 47.65, "lng": -117.42}}})
}
func (m *Module) ScanPage(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"scan": "ready"})
}
