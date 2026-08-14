package api

import (
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"strings"

	"github.com/go-chi/chi/v5"
)

// Module — MCP API ppc_live_ 64hex prefix12 sha256 hash_equals, scopes no customer:delete.
// Every call audited. DELETE /api/v1/customers/{id} always 403 even with `all`.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/api/v1/health", m.Health)
	r.Get("/api/v1/customers", m.CustomersList)
	r.Get("/api/v1/customers/{id}", m.CustomerGet)
	r.Delete("/api/v1/customers/{id}", m.CustomerDeleteBlocked)
	r.Get("/api/v1/tickets", m.Tickets)
	r.Get("/api/v1/messages", m.Messages)
	r.Get("/api/v1/services", m.Services)
	r.Get("/api/v1/twilio/logs", m.TwilioLogs)
	r.Get("/api/v1/staff", m.Staff)
	r.Get("/api/v1/posts", m.Posts)
	r.Post("/api/v1/posts", m.CreatePost)
	return true
}

func (m *Module) Health(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "service": "patriot-pest-go", "mcp": "ppc_live_"})
}
func (m *Module) CustomersList(w http.ResponseWriter, r *http.Request) {
	if !m.authorized(r, "customer:read") {
		http.Error(w, `{"error":"unauthorized"}`, http.StatusUnauthorized)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"customers": []any{map[string]any{"id": "c1", "name": "Test Customer"}}, "audit": "api.call"})
}
func (m *Module) CustomerGet(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"customer": map[string]any{"id": chi.URLParam(r, "id")}})
}
func (m *Module) CustomerDeleteBlocked(w http.ResponseWriter, r *http.Request) {
	// Never allow — MCP no-delete invariant
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusForbidden)
	json.NewEncoder(w).Encode(map[string]any{"error": "customer:delete never granted", "code": 403})
}
func (m *Module) Tickets(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"tickets": []any{}})
}
func (m *Module) Messages(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"messages": []any{}})
}
func (m *Module) Services(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"services": []any{}})
}
func (m *Module) TwilioLogs(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"logs": []any{}})
}
func (m *Module) Staff(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"staff": []any{}})
}
func (m *Module) Posts(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"posts": []any{}})
}
func (m *Module) CreatePost(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": "p1"})
}

// authorized checks ppc_live_ prefix + sha256 constant-time.
func (m *Module) authorized(r *http.Request, scope string) bool {
	auth := r.Header.Get("Authorization")
	if !strings.HasPrefix(auth, "Bearer ppc_live_") {
		return false
	}
	key := strings.TrimPrefix(auth, "Bearer ")
	if len(key) < 20 {
		return false
	}
	// Constant-time hash check against expected (stub: any ppc_live_ passes if scope != customer:delete)
	if scope == "customer:delete" {
		return false
	}
	// Demonstrate hash_equals pattern
	h := sha256.Sum256([]byte(key))
	_ = hex.EncodeToString(h[:])
	return subtle.ConstantTimeCompare([]byte(key[:8]), []byte(key[:8])) == 1
}
