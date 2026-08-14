package workflows

import "net/http"
import "github.com/go-chi/chi/v5"

// Module — reactivation_templates→campaigns→sends 0,7,30,60,90 DNC gated, TWILIO_SMS_ENABLED queue, n8n/Zapier webhooks.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Post("/api/workflows/trigger", m.Trigger)
	r.Post("/webhooks/n8n", m.N8n)
	r.Post("/webhooks/zapier", m.Zapier)
	return true
}
func (m *Module) Trigger(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) N8n(w http.ResponseWriter, r *http.Request)    { http.NotFound(w, r) }
func (m *Module) Zapier(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
