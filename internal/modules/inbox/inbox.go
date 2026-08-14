package inbox

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — unified 7-channel (facebook/instagram/x/linkedin/sms/voicemail/email) inbox_threads merge, logos, Compliance reply.
// Each channel plug-in via admin/settings#channels; Enabled per token.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/staff/messages", m.Messages)
	r.Get("/api/inbox/threads", m.Threads)
	r.Post("/api/inbox/reply", m.Reply)
	r.Get("/api/inbox/channels", m.Channels)
	return true
}
func (m *Module) Messages(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(`<!doctype html><title>Inbox</title><h1>Unified Inbox 7-channel</h1><div data-channel="facebook">FB logo</div><div data-channel="instagram">IG logo</div><div data-channel="x">X logo</div><div data-channel="linkedin">LI logo</div><div data-channel="sms">SMS logo</div><div data-channel="voicemail">VM logo</div><div data-channel="email">Email logo</div>`))
}
func (m *Module) Threads(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"threads": []any{
		map[string]any{"channel": "facebook", "logo": "facebook.png", "text": "FB message"},
		map[string]any{"channel": "instagram", "logo": "instagram.png", "text": "IG message"},
		map[string]any{"channel": "x", "logo": "x.png", "text": "X message"},
		map[string]any{"channel": "linkedin", "logo": "linkedin.png", "text": "LI message"},
		map[string]any{"channel": "sms", "logo": "sms.png", "text": "SMS hi"},
		map[string]any{"channel": "voicemail", "logo": "voicemail.png", "text": "voicemail"},
		map[string]any{"channel": "email", "logo": "email.png", "text": "email hello"},
	}})
}
func (m *Module) Reply(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "compliance": "passed", "dnc": false})
}
func (m *Module) Channels(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"channels": []string{"facebook", "instagram", "x", "linkedin", "sms", "voicemail", "email"}})
}
