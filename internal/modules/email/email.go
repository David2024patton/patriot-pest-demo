package email

import "net/http"
import "github.com/go-chi/chi/v5"

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
func (m *Module) Inbox(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) Send(w http.ResponseWriter, r *http.Request)  { http.NotFound(w, r) }
