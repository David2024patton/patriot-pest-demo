package health

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module is always enabled.
type Module struct{}

func (m *Module) Register(r chi.Router) bool {
	r.Get("/health", m.health)
	r.Get("/ready", m.ready)
	return true
}

func (m *Module) health(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"status":  "ok",
		"service": "patriot-pest-go",
		"time":    r.Context().Value("time"),
	})
}

func (m *Module) ready(w http.ResponseWriter, r *http.Request) {
	// TODO: check DB pool ping
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}
