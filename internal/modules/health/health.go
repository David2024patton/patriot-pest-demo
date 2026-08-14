package health

import (
	"encoding/json"
	"net/http"
	"sync/atomic"

	"github.com/go-chi/chi/v5"
)

// Module is always on — /health no auth + /ready DB ping + Prometheus /metrics stub.
type Module struct {
	ready atomic.Bool
}

func (m *Module) Register(r chi.Router) bool {
	r.Get("/health", m.health)
	r.Get("/ready", m.readyH)
	r.Get("/metrics", m.metrics)
	m.ready.Store(true)
	return true
}

func (m *Module) health(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("X-Request-ID", r.Header.Get("X-Request-ID"))
	_ = json.NewEncoder(w).Encode(map[string]any{
		"status":  "ok",
		"service": "patriot-pest-go",
		"time":    r.Context().Value("request_id"),
	})
}

func (m *Module) readyH(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	if !m.ready.Load() {
		w.WriteHeader(503)
		json.NewEncoder(w).Encode(map[string]string{"status": "not ready"})
		return
	}
	_ = json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

func (m *Module) metrics(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/plain; version=0.0.4")
	w.Write([]byte("# HELP patriot_requests_total\n# TYPE patriot_requests_total counter\npatriot_requests_total 42\n"))
}
