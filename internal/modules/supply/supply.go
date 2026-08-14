package supply

import (
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — supplies/supply_moves/supply_locations with truck/warehouse/hazmat split.
// FieldRoutes inventory sync or native. Photo check-in/out with gps, OSHA ledger.
// Locations: warehouse | truck:{tech_id} | hazmat (with sds_url, osha_class, hazmat=true).
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/admin/supplies", m.List)                              // filter ?location=
	r.Post("/admin/supplies", m.Create)
	r.Get("/admin/supplies/{id}/ledger", m.Ledger)                 // full history who/what/photo/gps/at
	r.Post("/api/supplies/{id}/checkin", m.Checkin)               // photo_url + qty + gps
	r.Post("/api/supplies/{id}/checkout", m.Checkout)             // photo_url + qty + gps + reason → audit_log supply.checkin|checkout
	return true
}

func (m *Module) List(w http.ResponseWriter, r *http.Request)     { http.NotFound(w, r) }
func (m *Module) Create(w http.ResponseWriter, r *http.Request)   { http.NotFound(w, r) }
func (m *Module) Ledger(w http.ResponseWriter, r *http.Request)   { http.NotFound(w, r) }
func (m *Module) Checkin(w http.ResponseWriter, r *http.Request)  { http.NotFound(w, r) }
func (m *Module) Checkout(w http.ResponseWriter, r *http.Request) { http.NotFound(w, r) }
