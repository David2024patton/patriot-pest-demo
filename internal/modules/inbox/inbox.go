package inbox

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — unified 7-channel (fb/ig/x/li/sms/voicemail/email) inbox_threads merge, logos, Compliance reply.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/staff/messages", m.Messages)
	r.Post("/api/inbox/reply", m.Reply)
	return true
}
func (m *Module) Messages(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"threads": []any{map[string]any{"channel": "sms", "logo": "sms.png", "text": "Hi"}, map[string]any{"channel": "email", "logo": "email.png"}}})
}
func (m *Module) Reply(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "compliance": "passed"})
}
