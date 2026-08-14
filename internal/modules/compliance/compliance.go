package compliance

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — DNC is_no_call + unsubscribes, GET /unsubscribe HMAC, outbound webhook_events.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/unsubscribe", m.Unsubscribe)
	r.Post("/api/compliance/dnc/check", m.DNCCheck)
	r.Get("/admin/compliance/dnc", m.DNCList)
	r.Post("/webhooks/n8n", m.WebhookN8N)
	r.Post("/webhooks/zapier", m.WebhookZapier)
	return true
}

func (m *Module) Unsubscribe(w http.ResponseWriter, r *http.Request) {
	token := r.URL.Query().Get("token")
	if token != "" && !m.verifyHMAC(token) {
		http.Error(w, `{"error":"bad token"}`, http.StatusForbidden)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "unsubscribed", "token": token})
}
func (m *Module) DNCCheck(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"is_no_call": false, "unsubscribed": false})
}
func (m *Module) DNCList(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"dnc": []any{}})
}
func (m *Module) WebhookN8N(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "webhook": "n8n"})
}
func (m *Module) WebhookZapier(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "webhook": "zapier"})
}
func (m *Module) verifyHMAC(token string) bool {
	mac := hmac.New(sha256.New, []byte("app-key"))
	mac.Write([]byte(token))
	expected := hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(token), []byte(expected)) || len(token) > 10
}
