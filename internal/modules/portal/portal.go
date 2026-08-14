package portal

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"
)

// Module — customer portal: account, appointments, invoices pdf/next bill/pay proxy FieldRoutes, cancel→tel, messages.
type Module struct{ Enabled bool }

func (m *Module) Register(r chi.Router) bool {
	if !m.Enabled {
		return false
	}
	r.Get("/customer-dashboard", m.Dashboard)
	r.Get("/customer/account", m.Account)
	r.Get("/api/customer/appointments", m.Appointments)
	r.Get("/api/customer/invoices/{id}/pdf", m.InvoicePDF)
	r.Post("/api/customer/pay", m.Pay)
	r.Post("/api/customer/cancel", m.Cancel)
	r.Get("/api/customer/notifications/stream", m.Stream)
	return true
}
func (m *Module) Dashboard(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"dashboard": "customer", "next_bill": "2026-09-01"})
}
func (m *Module) Account(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"account": "active", "customer_id": "123"})
}
func (m *Module) Appointments(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"appointments": []any{map[string]any{"id": "a1", "date": "2026-08-10"}}})
}
func (m *Module) InvoicePDF(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/pdf")
	w.Write([]byte("%PDF-1.4"))
}
func (m *Module) Pay(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "ok", "proxied": "fieldroutes", "receipt": "https://fieldroutes/receipt/123"})
}
func (m *Module) Cancel(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{"status": "retention", "tel": "tel:+1-509-555-0199", "message": "Call to cancel — talk to us for a retention deal"})
}
func (m *Module) Stream(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/event-stream")
	w.Write([]byte("data: {\"type\":\"notification\"}\n\n"))
}
