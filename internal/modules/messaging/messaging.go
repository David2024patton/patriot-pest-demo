package messaging

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — internal_messages + notifications{read} SSE bell in GET /staff/messages + board mentions.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/api/messages", m.List)
	r.Post("/api/messages", m.Send)
	r.Get("/api/notifications", m.Notifications)
	r.Get("/api/notifications/stream", m.Stream)
	r.Post("/api/notifications/{id}/read", m.MarkRead)
	return true
}

func (m *Module) List(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"messages": []any{map[string]any{"id": "m1", "text": "Hello team", "from": "admin"}}})
}
func (m *Module) Send(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": "m2"})
}
func (m *Module) Notifications(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"notifications": []any{map[string]any{"id": "n1", "type": "mention", "read": false}}})
}
func (m *Module) Stream(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Write([]byte("data: {\"type\":\"notification\",\"id\":\"n1\"}\n\n"))
}
func (m *Module) MarkRead(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": chi.URLParam(r, "id"), "read": true})
}
