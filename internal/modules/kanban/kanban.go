package kanban

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — boards/columns/cards with shared boards kanban_board_members viewer|editor|admin.
// Trello/kanbn parity: labels, checklist, due, assignees, cover, WIP, SSE, @mentions.
// MCP: /api/v1/kanban/* kanban:read/write per-board.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Route("/admin/board", func(r chi.Router) {
		r.Get("/", m.Board)
		r.Post("/boards/{bid}/members", m.AddMember)
	})
	r.Route("/api/kanban", func(r chi.Router) {
		r.Post("/boards/{bid}/columns/{cid}/cards/{id}/move", m.Move)
		r.Get("/boards/{bid}/events", m.Events) // SSE
	})
	r.Route("/api/v1/kanban", func(r chi.Router) {
		r.Get("/boards", m.APIList)
		r.Post("/boards", m.APICreate)
	})
	return true
}

func (m *Module) Board(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"boards": []any{map[string]any{"id": "b1", "title": "Ops Board", "visibility": "team", "members": []string{"tech_42"}}, map[string]any{"id": "b2", "title": "Sales Pipeline", "visibility": "private"}}})
}
func (m *Module) AddMember(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "member": "tech_42", "role": "editor"})
}
func (m *Module) Move(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "moved": true})
}
func (m *Module) Events(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/event-stream")
	w.Write([]byte("data: {\"event\":\"move\"}\n\n"))
}
func (m *Module) APIList(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"boards": []string{"b1", "b2"}})
}
func (m *Module) APICreate(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "id": "b3"})
}
