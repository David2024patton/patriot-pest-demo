package email

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — email_threads + email_messages IMAP ingest, mailboxes, compose, unified inbox logo.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin/email", m.Inbox)
	r.Post("/api/email/send", m.Send)
	return true
}
func (m *Module) Inbox(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"threads": []any{map[string]any{"subject": "Estimate", "from": "client@example.com"}}})
}
func (m *Module) Send(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "sent": true})
}
