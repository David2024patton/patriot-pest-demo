package inbox

import "net/http"
import "github.com/go-chi/chi/v5"

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
func (m *Module) Messages(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) Reply(w http.ResponseWriter, r *http.Request)    { http.NotFound(w, r) }
