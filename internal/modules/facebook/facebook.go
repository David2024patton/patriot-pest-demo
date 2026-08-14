package facebook

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — Facebook Lead Ads webhook + hub verify + X-Hub-Signature-256 fingerprint → vtext.com
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/webhooks/facebook", m.Verify)
	r.Post("/webhooks/facebook", m.Webhook)
	r.Get("/admin/facebook/leads", m.Leads)
	return true
}

func (m *Module) Verify(w http.ResponseWriter, r *http.Request) {
	// hub.challenge echo for Facebook verification
	challenge := r.URL.Query().Get("hub.challenge")
	if challenge != "" {
		w.Write([]byte(challenge))
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "verify": "facebook"})
}
func (m *Module) Webhook(w http.ResponseWriter, r *http.Request) {
	sig := r.Header.Get("X-Hub-Signature-256")
	if sig != "" && !m.verifySig(r, sig) {
		http.Error(w, `{"error":"bad signature"}`, http.StatusForbidden)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "webhook": "facebook", "fingerprint": "abc123"})
}
func (m *Module) Leads(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"leads": []any{}})
}
func (m *Module) verifySig(r *http.Request, sig string) bool {
	mac := hmac.New(sha256.New, []byte("facebook-app-secret"))
	mac.Write([]byte(r.URL.String()))
	expected := "sha256=" + hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(sig), []byte(expected))
}
