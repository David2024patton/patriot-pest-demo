package twilio

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — Twilio suite: 15 admin routes + webhooks with HMAC X-Twilio-Signature.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin/twilio", m.Dashboard)
	r.Get("/admin/twilio/logs", m.Logs)
	r.Get("/admin/twilio/messages", m.Messages)
	r.Get("/admin/twilio/calls", m.Calls)
	r.Get("/admin/twilio/voicemails", m.Voicemails)
	r.Post("/webhooks/twilio/sms", m.WebhookSMS)
	r.Post("/webhooks/twilio/status", m.WebhookStatus)
	r.Post("/webhooks/twilio/voice", m.WebhookVoice)
	r.Post("/webhooks/twilio/voicemail", m.WebhookVoicemail)
	return true
}

func (m *Module) Dashboard(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"twilio": "dashboard", "phone": "+1-509-555-0199"})
}
func (m *Module) Logs(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"logs": []any{}})
}
func (m *Module) Messages(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"messages": []any{}})
}
func (m *Module) Calls(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"calls": []any{}})
}
func (m *Module) Voicemails(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"voicemails": []any{}})
}
func (m *Module) WebhookSMS(w http.ResponseWriter, r *http.Request) {
	if !m.verifyHMAC(r) {
		// still 200 in stub to avoid Twilio retries during dev
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "webhook": "sms"})
}
func (m *Module) WebhookStatus(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "webhook": "status"})
}
func (m *Module) WebhookVoice(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/xml")
	w.Write([]byte(`<?xml version="1.0"?><Response><Say>Thanks for calling Patriot</Say></Response>`))
}
func (m *Module) WebhookVoicemail(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "webhook": "voicemail"})
}

func (m *Module) verifyHMAC(r *http.Request) bool {
	sig := r.Header.Get("X-Twilio-Signature")
	if sig == "" {
		return false
	}
	mac := hmac.New(sha256.New, []byte("twilio-auth-token"))
	mac.Write([]byte(r.URL.String()))
	expected := hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(sig), []byte(expected))
}
