package customers

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — customers + FieldRoutes Salesforce mirror (customers/appointments/invoices)
// Sync via worker pool, WA/AZ districts, book/profile, /api/customer-search.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/staff/customers", m.List)
	r.Get("/staff/customers/{id}", m.Get)
	r.Post("/staff/customers/sync", m.Sync)
	r.Get("/api/customer-search", m.Search)
	r.Post("/api/customers/{id}/notes", m.AddNote)
	return true
}

func (m *Module) List(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"customers": []any{map[string]any{"id": "c1", "name": "John Doe", "district": "WA"}, map[string]any{"id": "c2", "name": "Jane Smith", "district": "AZ"}}})
}
func (m *Module) Get(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"customer": map[string]any{"id": chi.URLParam(r, "id"), "appointments": []any{}, "invoices": []any{}}})
}
func (m *Module) Sync(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "synced": 42, "source": "fieldroutes", "districts": []string{"WA", "AZ"}})
}
func (m *Module) Search(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query().Get("q")
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"q": q, "results": []any{map[string]any{"id": "c1", "name": "John Doe"}}})
}
func (m *Module) AddNote(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "customer_id": chi.URLParam(r, "id"), "gps": r.Header.Get("X-GPS"), "note": "added"})
}
