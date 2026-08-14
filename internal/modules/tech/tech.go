package tech

import "net/http"
import "github.com/go-chi/chi/v5"

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
func (m *Module) Index(w http.ResponseWriter, r *http.Request)  { http.NotFound(w, r) }
func (m *Module) Routes(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) ScanPage(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
