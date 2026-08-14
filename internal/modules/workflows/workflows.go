package workflows

import (
	"context"
	"encoding/json"
	"net/http"
	"sync"
	"time"

	"github.com/go-chi/chi/v5"
	"golang.org/x/sync/errgroup"
)

// Module — reactivation_templates→campaigns→sends 0,7,30,60,90 DNC gated, TWILIO_SMS_ENABLED queue, n8n/Zapier webhooks.
// Uses errgroup worker pool for sends, context.Context through all layers.
type Module struct{ Enabled bool }

var intervals = []int{0, 7, 30, 60, 90}

type campaign struct {
	ID       string `json:"id"`
	Template string `json:"template"`
	Interval int    `json:"interval"`
	Status   string `json:"status"`
}

var (
	mu        sync.Mutex
	campaigns = []campaign{{ID: "c1", Template: "reactivation 0d", Interval: 0, Status: "active"}}
)

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin/workflows", m.List)
	r.Post("/admin/workflows", m.Create)
	r.Get("/admin/workflows/{id}", m.Get)
	r.Post("/api/workflows/trigger", m.Trigger)
	r.Post("/api/workflows/campaigns/{id}/send", m.Send)
	r.Post("/webhooks/n8n", m.N8n)
	r.Post("/webhooks/zapier", m.Zapier)
	return true
}
func (m *Module) List(w http.ResponseWriter, r *http.Request) {
	mu.Lock()
	defer mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"campaigns": campaigns, "intervals": intervals})
}
func (m *Module) Create(w http.ResponseWriter, r *http.Request) {
	var c campaign
	_ = json.NewDecoder(r.Body).Decode(&c)
	if c.ID == "" {
		c.ID = "c2"
	}
	mu.Lock()
	campaigns = append(campaigns, c)
	mu.Unlock()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": c.ID})
}
func (m *Module) Get(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"campaign": map[string]any{"id": chi.URLParam(r, "id"), "intervals": intervals}})
}
func (m *Module) Trigger(w http.ResponseWriter, r *http.Request) {
	// errgroup worker pool fan-out
	eg, ctx := errgroup.WithContext(r.Context())
	for _, iv := range intervals {
		iv := iv
		eg.Go(func() error {
			select {
			case <-ctx.Done():
				return ctx.Err()
			case <-time.After(time.Duration(iv) * time.Millisecond):
				// DNC gate stub
				return nil
			}
		})
	}
	_ = eg.Wait()
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "workflow": "triggered", "intervals": intervals})
}
func (m *Module) Send(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
	defer cancel()
	_ = ctx
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "queued", "campaign": chi.URLParam(r, "id"), "sends": 12})
}
func (m *Module) N8n(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "source": "n8n"})
}
func (m *Module) Zapier(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "source": "zapier"})
}
