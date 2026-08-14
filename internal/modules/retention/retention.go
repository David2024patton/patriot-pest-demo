package retention

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — beacon POST /api/track/view|event|session_end IP→user stitch, GET /api/retention/summary + /admin/retention.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Post("/api/track/view", m.TrackView)
	r.Post("/api/track/event", m.TrackEvent)
	r.Post("/api/track/session_end", m.SessionEnd)
	r.Get("/api/retention/summary", m.Summary)
	r.Get("/admin/retention", m.Admin)
	return true
}

func (m *Module) TrackView(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "event": "view"})
}
func (m *Module) TrackEvent(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "event": "event"})
}
func (m *Module) SessionEnd(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "event": "session_end"})
}
func (m *Module) Summary(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"summary": map[string]any{"views": 1234, "conversions": 56}})
}
func (m *Module) Admin(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"retention": map[string]any{"beacon": true, "stitched": 42}})
}
