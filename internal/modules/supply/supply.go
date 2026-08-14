package supply

import "net/http"

import "github.com/go-chi/chi/v5"

// Module — supplies/supply_moves; FieldRoutes inventory sync or native.
// GET /admin/supplies CRUD + low-stock alerts, link to kanban/appointments.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin/supplies", m.List)
	r.Post("/admin/supplies", m.Create)
	return true
}
func (m *Module) List(w http.ResponseWriter, r *http.Request)   { http.NotFound(w, r) }
func (m *Module) Create(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
