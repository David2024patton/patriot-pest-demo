package kanban

import (
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

func (m *Module) Board(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) AddMember(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) Move(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) Events(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) APIList(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
func (m *Module) APICreate(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
